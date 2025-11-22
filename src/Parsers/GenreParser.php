<?php

namespace RekordboxReader\Parsers;

class GenreParser {
    private $pdbParser;
    private $logger;
    private $genres;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->genres = [];
    }

    public function parseGenres() {
        $genresTable = $this->pdbParser->getTable(1);
        
        if (!$genresTable) {
            return [];
        }

        $genres = $this->extractRows($genresTable);
        
        $this->genres = [];
        $index = 1;
        foreach ($genres as $genre) {
            $this->genres[$index] = $genre['name'];
            $this->genres[$genre['id']] = $genre['name'];
            $index++;
        }
        
        // Direct extraction fallback if extractRows fails
        if (empty($this->genres)) {
            $firstPage = $genresTable['first_page'];
            $lastPage = $genresTable['last_page'];
            
            for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
                $pageData = $this->pdbParser->readPage($pageIdx);
                if (!$pageData) continue;
                
                // Check if this is a data page
                if (strlen($pageData) >= 28) {
                    $flags = ord($pageData[27]);
                    if (($flags & 0x40) == 0) {
                        // Try direct extraction at known offsets
                        $offset = 40;
                        if ($offset + 10 < strlen($pageData)) {
                            $id = unpack('v', substr($pageData, $offset, 2))[1];
                            list($str, $newOff) = $this->pdbParser->extractString($pageData, $offset + 4);
                            if ($str && $id > 0) {
                                $this->genres[$id] = trim($str);
                            }
                        }
                    }
                }
            }
        }

        return $this->genres;
    }

    private function extractRows($table) {
        $rows = [];
        
        $firstPage = $table['first_page'];
        $lastPage = $table['last_page'];

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $pageRows = $this->parsePage($pageData);
            $rows = array_merge($rows, $pageRows);
        }

        return $rows;
    }

    private function parsePage($pageData) {
        $rows = [];

        try {
            if (strlen($pageData) < 48) {
                return $rows;
            }

            $pageHeader = unpack(
                'Vgap/Vpage_idx/Vtype/Vnext/Vu1/Vu2/' .
                'Cnum_rows/Cu1/Cu2/Cflags',
                substr($pageData, 0, 28)
            );

            $isDataPage = ($pageHeader['flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                return $rows;
            }

            $numRows = $pageHeader['num_rows'];
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
                    // Check presence bit for this row
                    $presenceBit = 1 << $rowIdx;
                    if (($presenceFlags & $presenceBit) == 0) {
                        // Row not present, skip
                        continue;
                    }
                    
                    $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                    
                    if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                    $rowOffset = $rowOffsetData[1];
                    
                    if ($rowOffset == 0) {
                        continue;
                    }
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset >= $pageSize || $actualRowOffset + 20 > $pageSize) {
                        continue;
                    }

                    $row = $this->parseRow($pageData, $actualRowOffset);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
            }

        } catch (\Exception $e) {
            return $rows;
        }

        return $rows;
    }

    private function parseRow($pageData, $offset) {
        try {
            if ($offset + 8 > strlen($pageData)) {
                return null;
            }
            
            // Genre row structure per Kaitai spec:
            // 0x00: id (u4)
            // 0x04: name (device_sql_string)
            
            $idData = unpack('V', substr($pageData, $offset, 4));
            $id = $idData[1];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            // Extract name at offset+4
            list($name, $newOffset) = $this->pdbParser->extractString($pageData, $offset + 4);
            
            if ($name && strlen(trim($name)) > 0 && $id > 0) {
                return ['id' => $id, 'name' => trim($name)];
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getGenreName($genreId) {
        return $this->genres[$genreId] ?? "";
    }
}
