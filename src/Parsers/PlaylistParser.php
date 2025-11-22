<?php

namespace RekordboxReader\Parsers;

class PlaylistParser {
    private $pdbParser;
    private $logger;
    private $playlists;
    private $validPlaylists;
    private $corruptPlaylists;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->playlists = [];
        $this->validPlaylists = 0;
        $this->corruptPlaylists = 0;
    }

    public function parsePlaylists() {
        $playlistTree = $this->pdbParser->getTable(PdbParser::TABLE_PLAYLIST_TREE);
        $playlistEntries = $this->pdbParser->getTable(PdbParser::TABLE_PLAYLIST_ENTRIES);

        if (!$playlistTree) {
            if ($this->logger) {
                $this->logger->warning("Playlist tree table not found");
            }
            return [];
        }

        if ($this->logger) {
            $this->logger->info("Parsing playlists dari database...");
        }

        $this->playlists = $this->extractPlaylistTree($playlistTree, $playlistEntries);

        if ($this->logger) {
            $this->logger->info(
                "Playlist parsing selesai: {$this->validPlaylists} valid, " .
                "{$this->corruptPlaylists} corrupt (dilewati)"
            );
        }

        return $this->playlists;
    }

    private function extractPlaylistTree($treeTable, $entriesTable) {
        $playlists = [];
        
        $firstPage = $treeTable['first_page'];
        $lastPage = $treeTable['last_page'];

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $pagePlaylists = $this->parsePlaylistPage($pageData, $entriesTable, $pageIdx);
            $playlists = array_merge($playlists, $pagePlaylists);
        }

        return $playlists;
    }

    private function parsePlaylistPage($pageData, $entriesTable, $pageIdx) {
        $playlists = [];

        try {
            if (strlen($pageData) < 48) {
                return $playlists;
            }

            $pageHeader = unpack(
                'Vgap/' .
                'Vpage_index/' .
                'Vtype/' .
                'Vnext_page/' .
                'Vunknown1/' .
                'Vunknown2/' .
                'Cnum_rows_small/' .
                'Cu3/' .
                'Cu4/' .
                'Cpage_flags/' .
                'vfree_size/' .
                'vused_size/' .
                'vu5/' .
                'vnum_rows_large',
                substr($pageData, 0, 36)
            );

            // Check if this is a data page
            $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                return $playlists;
            }
            
            // Verify this is actually a playlist_tree page (type 7)
            if ($pageHeader['type'] != 7) {
                return $playlists;
            }

            $numRows = $pageHeader['num_rows_small'];
            if ($pageHeader['num_rows_large'] > $pageHeader['num_rows_small'] && 
                $pageHeader['num_rows_large'] != 0x1fff) {
                $numRows = $pageHeader['num_rows_large'];
            }

            if ($numRows == 0) {
                return $playlists;
            }

            $heapPos = 40;
            $pageSize = strlen($pageData);
            $numGroups = intval(($numRows - 1) / 16) + 1;
            
            for ($groupIdx = 0; $groupIdx < $numGroups; $groupIdx++) {
                $base = $pageSize - ($groupIdx * 0x24);
                $flagsOffset = $base - 4;
                
                if ($flagsOffset < 0 || $flagsOffset + 2 > $pageSize) {
                    continue;
                }
                
                $presenceFlags = unpack('v', substr($pageData, $flagsOffset, 2))[1];
                
                // Skip group if no rows are present
                if ($presenceFlags == 0) {
                    continue;
                }
                
                $rowsInGroup = min(16, $numRows - ($groupIdx * 16));
                
                for ($rowIdx = 0; $rowIdx < $rowsInGroup; $rowIdx++) {
                    // Check if this row is present
                    $present = (($presenceFlags >> $rowIdx) & 1) != 0;
                    if (!$present) {
                        continue;
                    }
                    
                    $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                    
                    if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                    $rowOffset = $rowOffsetData[1];
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset + 24 > $pageSize) {
                        continue;
                    }
                    
                    try {
                        $playlist = $this->parsePlaylistRow($pageData, $actualRowOffset, $entriesTable);
                        if ($playlist && $playlist['id'] > 0) {
                            $playlists[] = $playlist;
                            $this->validPlaylists++;
                        }
                    } catch (\Exception $e) {
                        $this->corruptPlaylists++;
                        if ($this->logger) {
                            $this->logger->debug("Corrupt playlist at page {$pageIdx}, row {$rowIdx}: " . $e->getMessage());
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->corruptPlaylists++;
            if ($this->logger) {
                $this->logger->debug("Error parsing playlist page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $playlists;
    }

    private function parsePlaylistRow($pageData, $offset, $entriesTable) {
        if ($offset + 24 > strlen($pageData)) {
            return null;
        }

        // Parse according to Kaitai Struct playlist_tree_row definition
        // Offset 0x00: parent_id (u4)
        // Offset 0x04: unknown (u4) 
        // Offset 0x08: sort_order (u4)
        // Offset 0x0C: id (u4)
        // Offset 0x10: raw_is_folder (u4)
        // Offset 0x14: name (device_sql_string)
        
        $fixedData = unpack(
            'Vparent_id/' .
            'Vunknown/' .
            'Vsort_order/' .
            'Vid/' .
            'Vraw_is_folder',
            substr($pageData, $offset, 20)
        );

        $playlistId = $fixedData['id'] ?? 0;
        $parentId = $fixedData['parent_id'] ?? 0;
        $sortOrder = $fixedData['sort_order'] ?? 0;
        $isFolder = ($fixedData['raw_is_folder'] ?? 0) != 0;

        // Extract name using device_sql_string at offset 0x14 (20 bytes from start)
        $nameOffset = $offset + 20;
        list($name, $newOffset) = $this->pdbParser->extractString($pageData, $nameOffset);
        
        // Clean the extracted name
        $name = trim($name);
        
        // Remove any null bytes
        $nullPos = strpos($name, "\x00");
        if ($nullPos !== false) {
            $name = substr($name, 0, $nullPos);
        }
        
        // Final cleanup
        $name = trim($name);

        $entries = [];
        if ($entriesTable && $playlistId > 0) {
            $rawEntries = $this->getPlaylistEntries($playlistId, $entriesTable);
            
            // Sort entries by position and extract track IDs
            usort($rawEntries, function($a, $b) {
                return $a['position'] - $b['position'];
            });
            
            foreach ($rawEntries as $entry) {
                $entries[] = $entry['track_id'];
            }
        }

        return [
            'id' => $playlistId,
            'name' => !empty($name) ? $name : 'Unnamed Playlist',
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'is_folder' => $isFolder,
            'entries' => $entries,
            'track_count' => count($entries)
        ];
    }

    private function getPlaylistEntries($playlistId, $entriesTable) {
        $entries = [];

        try {
            $firstPage = $entriesTable['first_page'];
            $lastPage = $entriesTable['last_page'];

            for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
                $pageData = $this->pdbParser->readPage($pageIdx);
                if (!$pageData) {
                    continue;
                }

                $pageEntries = $this->parseEntriesPage($pageData, $playlistId);
                $entries = array_merge($entries, $pageEntries);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error getting playlist entries: " . $e->getMessage());
            }
        }

        return $entries;
    }

    private function parseEntriesPage($pageData, $targetPlaylistId) {
        $entries = [];

        if (strlen($pageData) < 48) {
            return $entries;
        }

        $pageHeader = unpack(
            'Vgap/' .
            'Vpage_index/' .
            'Vtype/' .
            'Vnext_page/' .
            'Vunknown1/' .
            'Vunknown2/' .
            'Cnum_rows_small/' .
            'Cu3/' .
            'Cu4/' .
            'Cpage_flags/' .
            'vfree_size/' .
            'vused_size/' .
            'vu5/' .
            'vnum_rows_large',
            substr($pageData, 0, 36)
        );

        $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
        
        if (!$isDataPage) {
            return $entries;
        }

        $numRows = $pageHeader['num_rows_small'];
        if ($pageHeader['num_rows_large'] > $pageHeader['num_rows_small'] && 
            $pageHeader['num_rows_large'] != 0x1fff) {
            $numRows = $pageHeader['num_rows_large'];
        }

        if ($numRows == 0) {
            return $entries;
        }

        $heapPos = 40;
        $pageSize = strlen($pageData);
        $numGroups = intval(($numRows - 1) / 16) + 1;

        for ($groupIdx = 0; $groupIdx < $numGroups; $groupIdx++) {
            $base = $pageSize - ($groupIdx * 0x24);
            $flagsOffset = $base - 4;
            
            if ($flagsOffset < 0 || $flagsOffset + 2 > $pageSize) {
                continue;
            }
            
            $presenceFlags = unpack('v', substr($pageData, $flagsOffset, 2))[1];
            $rowsInGroup = min(16, $numRows - ($groupIdx * 16));
            
            for ($rowIdx = 0; $rowIdx < $rowsInGroup; $rowIdx++) {
                $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                
                if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                    continue;
                }
                
                $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                $rowOffset = $rowOffsetData[1];
                
                $present = (($presenceFlags >> $rowIdx) & 1) != 0;
                if (!$present) continue;
                
                $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                if ($actualRowOffset + 12 > $pageSize) continue;

                // Parse according to Kaitai Struct playlist_entry_row definition
                // Offset 0x00: entry_index (u4) - position in playlist
                // Offset 0x04: track_id (u4)
                // Offset 0x08: playlist_id (u4)
                $entryData = unpack(
                    'Ventry_index/' .
                    'Vtrack_id/' .
                    'Vplaylist_id',
                    substr($pageData, $actualRowOffset, 12)
                );

                // Only include valid track IDs (> 0) for the target playlist
                if ($entryData['playlist_id'] == $targetPlaylistId && $entryData['track_id'] > 0) {
                    $entries[] = [
                        'position' => $entryData['entry_index'],
                        'track_id' => $entryData['track_id']
                    ];
                }
            }
        }

        return $entries;
    }

    public function getStats() {
        return [
            'total_playlists' => count($this->playlists),
            'valid_playlists' => $this->validPlaylists,
            'corrupt_playlists' => $this->corruptPlaylists
        ];
    }

    public function getPlaylists() {
        return $this->playlists;
    }
}
