<?php

namespace RekordboxReader\Parsers;

class ColorParser {
    private $pdbParser;
    private $logger;
    private $colors;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->colors = [];
    }

    public function parseColors() {
        $colorsTable = $this->pdbParser->getTable(PdbParser::TABLE_COLORS);
        
        if (!$colorsTable) {
            if ($this->logger) {
                $this->logger->warning("Colors table not found in database");
            }
            return [];
        }

        $colors = $this->extractRows($colorsTable);
        
        $this->colors = [];
        foreach ($colors as $color) {
            $this->colors[$color['id']] = $color['name'];
        }

        if ($this->logger) {
            $this->logger->info("Parsed " . count($this->colors) . " colors from database");
        }

        return $this->colors;
    }

    private function extractRows($table) {
        $rows = [];
        
        $firstPage = $table['first_page'];
        $lastPage = $table['last_page'];

        $currentPageIdx = $firstPage;
        $visitedPages = [];
        $maxIterations = 1000;
        $iteration = 0;

        while ($currentPageIdx > 0 && $iteration < $maxIterations) {
            if (isset($visitedPages[$currentPageIdx])) {
                break;
            }
            $visitedPages[$currentPageIdx] = true;
            
            $pageData = $this->pdbParser->readPage($currentPageIdx);
            if (!$pageData) {
                // Cannot read page - we can't get next_page pointer without the page header
                // Abort parsing this table to avoid reading from wrong tables
                if ($this->logger) {
                    $this->logger->debug("Could not read page $currentPageIdx, stopping table parsing");
                }
                break;
            }
            
            $pageHeader = unpack(
                'Vgap/' .
                'Vpage_index/' .
                'Vtype/' .
                'Vnext_page',
                substr($pageData, 0, 16)
            );
            
            $pageRows = $this->parsePage($pageData, $currentPageIdx);
            $rows = array_merge($rows, $pageRows);
            
            if ($currentPageIdx == $lastPage) {
                break;
            }
            
            $currentPageIdx = $pageHeader['next_page'];
            $iteration++;
        }

        return $rows;
    }

    private function parsePage($pageData, $pageIdx) {
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

                    $row = $this->parseRow($pageData, $actualRowOffset);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
            }

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing color page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parseRow($pageData, $offset) {
        try {
            if ($offset + 10 > strlen($pageData)) {
                return null;
            }
            
            $id = unpack('v', substr($pageData, $offset + 5, 2))[1];
            
            if ($id == 0 || $id > 255) {
                return null;
            }
            
            list($name, $newOffset) = $this->pdbParser->extractString($pageData, $offset + 8);
            
            if ($name && strlen($name) > 0) {
                return ['id' => $id, 'name' => trim($name)];
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getColorName($colorId) {
        return $this->colors[$colorId] ?? "";
    }
}
