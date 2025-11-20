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

            $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                if ($this->logger) {
                    $this->logger->debug("Playlist page {$pageIdx} is not a data page, skipping");
                }
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
                $rowsInGroup = min(16, $numRows - ($groupIdx * 16));
                
                for ($rowIdx = 0; $rowIdx < $rowsInGroup; $rowIdx++) {
                    $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                    
                    if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                    $rowOffset = $rowOffsetData[1];
                    
                    $present = (($presenceFlags >> $rowIdx) & 1) != 0;
                    
                    if (!$present) {
                        continue;
                    }
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset + 50 > $pageSize) {
                        continue;
                    }
                    
                    try {
                        $playlist = $this->parsePlaylistRow($pageData, $actualRowOffset, $entriesTable);
                        if ($playlist) {
                            $playlists[] = $playlist;
                            $this->validPlaylists++;
                        }
                    } catch (\Exception $e) {
                        $this->corruptPlaylists++;
                        if ($this->logger) {
                            $this->logger->warning("Corrupt playlist at page {$pageIdx}, row {$rowIdx}: " . $e->getMessage());
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->corruptPlaylists++;
            if ($this->logger) {
                $this->logger->warning("Error parsing playlist page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $playlists;
    }

    private function parsePlaylistRow($pageData, $offset, $entriesTable) {
        if ($offset + 60 > strlen($pageData)) {
            return null;
        }

        $name = '';
        $playlistId = 1;
        
        for ($scanOffset = $offset + 2; $scanOffset < $offset + 150; $scanOffset++) {
            if ($scanOffset >= strlen($pageData)) break;
            
            $flags = ord($pageData[$scanOffset]);
            
            if (($flags & 0x40) == 0) {
                $len = $flags & 0x7F;
                if ($len >= 2 && $len < 50 && ($scanOffset + $len + 1) <= strlen($pageData)) {
                    $str = substr($pageData, $scanOffset + 1, $len);
                    
                    $nullPos = strpos($str, "\x00");
                    if ($nullPos !== false) {
                        $str = substr($str, 0, $nullPos);
                    }
                    
                    $str = trim($str);
                    
                    if (strlen($str) >= 2 && preg_match('/[A-Za-z0-9]/', $str) && !ctype_digit($str)) {
                        $name = $str;
                        break;
                    }
                }
            }
        }

        $parentId = 0;
        $isFolder = false;

        $entries = [];
        if ($entriesTable && $playlistId > 0) {
            $entries = $this->getPlaylistEntries($playlistId, $entriesTable);
        }

        return [
            'id' => $playlistId,
            'name' => $name ?: 'Unnamed Playlist',
            'parent_id' => $parentId,
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

                $entryData = unpack(
                    'Ventry_id/' .
                    'Vtrack_id/' .
                    'Vplaylist_id',
                    substr($pageData, $actualRowOffset, 12)
                );

                if ($entryData['playlist_id'] == $targetPlaylistId) {
                    $entries[] = $entryData['track_id'];
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
