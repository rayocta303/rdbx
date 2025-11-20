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
            $id = unpack('v', substr($pageData, $offset, 2))[1];
            
            $name = '';
            
            for ($scan = $offset + 2; $scan < $offset + 150; $scan++) {
                if ($scan >= strlen($pageData)) break;
                
                $flags = ord($pageData[$scan]);
                if (($flags & 0x40) == 0) {
                    $len = $flags & 0x7F;
                    if ($len >= 3 && $len < 100 && ($scan + $len + 1) <= strlen($pageData)) {
                        $str = substr($pageData, $scan + 1, $len);
                        
                        $nullPos = strpos($str, "\x00");
                        if ($nullPos !== false) {
                            $str = substr($str, 0, $nullPos);
                        }
                        
                        $str = trim($str);
                        
                        if (strlen($str) >= 3 && preg_match('/[A-Za-z]/', $str)) {
                            $name = $str;
                            break;
                        }
                    }
                }
            }

            if ($name && $id > 0) {
                return ['id' => $id, 'name' => $name];
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
