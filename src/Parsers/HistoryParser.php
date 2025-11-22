<?php

namespace RekordboxReader\Parsers;

class HistoryParser {
    private $pdbParser;
    private $logger;
    private $historyPlaylists;
    private $historyEntries;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->historyPlaylists = [];
        $this->historyEntries = [];
    }

    public function parseHistoryPlaylists() {
        $historyPlaylistsTable = $this->pdbParser->getTable(PdbParser::TABLE_HISTORY_PLAYLISTS);
        
        if (!$historyPlaylistsTable) {
            if ($this->logger) {
                $this->logger->info("History playlists table not found in database");
            }
            return [];
        }

        $playlists = $this->extractPlaylistRows($historyPlaylistsTable);
        
        $this->historyPlaylists = [];
        foreach ($playlists as $playlist) {
            $this->historyPlaylists[$playlist['id']] = $playlist['name'];
        }

        if ($this->logger) {
            $this->logger->info("Parsed " . count($this->historyPlaylists) . " history playlists from database");
        }

        return $this->historyPlaylists;
    }

    public function parseHistoryEntries() {
        $historyEntriesTable = $this->pdbParser->getTable(PdbParser::TABLE_HISTORY_ENTRIES);
        
        if (!$historyEntriesTable) {
            if ($this->logger) {
                $this->logger->info("History entries table not found in database");
            }
            return [];
        }

        $entries = $this->extractEntryRows($historyEntriesTable);
        
        $this->historyEntries = [];
        foreach ($entries as $entry) {
            $playlistId = $entry['playlist_id'];
            if (!isset($this->historyEntries[$playlistId])) {
                $this->historyEntries[$playlistId] = [];
            }
            $this->historyEntries[$playlistId][] = [
                'track_id' => $entry['track_id'],
                'entry_index' => $entry['entry_index']
            ];
        }

        foreach ($this->historyEntries as $playlistId => &$entries) {
            usort($entries, function($a, $b) {
                return $a['entry_index'] - $b['entry_index'];
            });
        }

        if ($this->logger) {
            $this->logger->info("Parsed " . count($entries) . " history entries from database");
        }

        return $this->historyEntries;
    }

    private function extractPlaylistRows($table) {
        $rows = [];
        
        $firstPage = $table['first_page'];
        $lastPage = $table['last_page'];

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $pageRows = $this->parsePlaylistPage($pageData, $pageIdx);
            $rows = array_merge($rows, $pageRows);
        }

        return $rows;
    }

    private function extractEntryRows($table) {
        $rows = [];
        
        $firstPage = $table['first_page'];
        $lastPage = $table['last_page'];

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $pageRows = $this->parseEntryPage($pageData, $pageIdx);
            $rows = array_merge($rows, $pageRows);
        }

        return $rows;
    }

    private function parsePlaylistPage($pageData, $pageIdx) {
        $rows = [];

        try {
            if (strlen($pageData) < 48) {
                return $rows;
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
                'Cpage_flags',
                substr($pageData, 0, 28)
            );

            $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                return $rows;
            }

            $numRows = $pageHeader['num_rows_small'];
            
            $numRowsLarge = 0;
            if (strlen($pageData) >= 40) {
                $numRowsLarge = unpack('v', substr($pageData, 34, 2))[1];
            }
            
            if ($numRowsLarge > $numRows && $numRowsLarge != 0x1fff) {
                $numRows = $numRowsLarge;
            }
            
            if ($numRows == 0) {
                return $rows;
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
                    $isPresent = ($presenceFlags >> $rowIdx) & 1;
                    if (!$isPresent) {
                        continue;
                    }
                    
                    $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                    
                    if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                    $rowOffset = $rowOffsetData[1];
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset >= $pageSize || $actualRowOffset + 20 > $pageSize) {
                        continue;
                    }

                    $row = $this->parsePlaylistRow($pageData, $actualRowOffset);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
            }

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing history playlist page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parseEntryPage($pageData, $pageIdx) {
        $rows = [];

        try {
            if (strlen($pageData) < 48) {
                return $rows;
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
                'Cpage_flags',
                substr($pageData, 0, 28)
            );

            $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                return $rows;
            }

            $numRows = $pageHeader['num_rows_small'];
            
            $numRowsLarge = 0;
            if (strlen($pageData) >= 40) {
                $numRowsLarge = unpack('v', substr($pageData, 34, 2))[1];
            }
            
            if ($numRowsLarge > $numRows && $numRowsLarge != 0x1fff) {
                $numRows = $numRowsLarge;
            }
            
            if ($numRows == 0) {
                return $rows;
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
                    $isPresent = ($presenceFlags >> $rowIdx) & 1;
                    if (!$isPresent) {
                        continue;
                    }
                    
                    $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                    
                    if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                    $rowOffset = $rowOffsetData[1];
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset >= $pageSize || $actualRowOffset + 12 > $pageSize) {
                        continue;
                    }

                    $row = $this->parseEntryRow($pageData, $actualRowOffset);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
            }

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing history entry page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parsePlaylistRow($pageData, $offset) {
        try {
            if ($offset + 20 > strlen($pageData)) {
                return null;
            }
            
            $id = unpack('V', substr($pageData, $offset, 4))[1];
            
            if ($id == 0) {
                return null;
            }
            
            $nameOffset = $offset + 4;
            for ($scan = $nameOffset; $scan < $offset + 40 && $scan < strlen($pageData); $scan++) {
                $byte = ord($pageData[$scan]);
                if ($byte == 0x40 || $byte == 0x90 || ($byte > 0 && $byte < 0x80 && ($byte & 1))) {
                    list($name, $newOffset) = $this->pdbParser->extractString($pageData, $scan);
                    if ($name && strlen($name) > 0) {
                        return ['id' => $id, 'name' => trim($name)];
                    }
                    break;
                }
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseEntryRow($pageData, $offset) {
        try {
            if ($offset + 12 > strlen($pageData)) {
                return null;
            }
            
            $trackId = unpack('V', substr($pageData, $offset, 4))[1];
            $playlistId = unpack('V', substr($pageData, $offset + 4, 4))[1];
            $entryIndex = unpack('V', substr($pageData, $offset + 8, 4))[1];
            
            if ($trackId == 0 || $playlistId == 0) {
                return null;
            }
            
            return [
                'track_id' => $trackId,
                'playlist_id' => $playlistId,
                'entry_index' => $entryIndex
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getHistoryPlaylists() {
        return $this->historyPlaylists;
    }

    public function getHistoryEntries() {
        return $this->historyEntries;
    }

    public function getHistoryPlaylistName($playlistId) {
        return $this->historyPlaylists[$playlistId] ?? "";
    }
}
