<?php

namespace RekordboxReader\Parsers;

class ColumnsParser {
    private $pdbParser;
    private $logger;
    private $columns;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->columns = [];
    }

    public function parseColumns() {
        $columnsTable = $this->pdbParser->getTable(PdbParser::TABLE_COLUMNS);
        
        if (!$columnsTable) {
            if ($this->logger) {
                $this->logger->info("Columns table not found in database");
            }
            return [];
        }

        $columns = $this->extractRows($columnsTable);
        
        $this->columns = $columns;

        if ($this->logger) {
            $this->logger->info("Parsed " . count($this->columns) . " column entries from database");
        }

        return $this->columns;
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

            $pageRows = $this->parsePage($pageData, $pageIdx);
            $rows = array_merge($rows, $pageRows);
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
                    
                    if ($actualRowOffset >= $pageSize) {
                        continue;
                    }

                    $row = $this->parseRow($pageData, $actualRowOffset, $pageSize);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
            }

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing columns page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parseRow($pageData, $offset, $pageSize) {
        try {
            $maxLength = min(200, $pageSize - $offset);
            
            if ($maxLength < 4) {
                return null;
            }
            
            $rowData = substr($pageData, $offset, $maxLength);
            $hexDump = bin2hex($rowData);
            
            $result = [
                'offset' => $offset,
                'raw_hex' => substr($hexDump, 0, 64),
                'data' => []
            ];
            
            // Parse subtype (first 2 bytes)
            if ($maxLength >= 2) {
                $subtype = unpack('v', substr($rowData, 0, 2))[1];
                $result['data']['subtype'] = $subtype;
                $result['data']['id'] = $subtype;
            }
            
            // Try to identify column type based on pattern
            if ($maxLength >= 4) {
                $type_indicator = unpack('v', substr($rowData, 2, 2))[1];
                $result['data']['type_indicator'] = $type_indicator;
                
                // Attempt to classify column type
                if ($type_indicator == 0) {
                    $result['data']['column_type'] = 'Unknown';
                } else {
                    $result['data']['column_type'] = 'Type_' . $type_indicator;
                }
            }
            
            // Try to extract string data (similar to other parsers)
            // Look for string offset at position 4-6
            if ($maxLength >= 8) {
                $stringOffset = unpack('v', substr($rowData, 4, 2))[1];
                
                if ($stringOffset > 0 && $stringOffset < $maxLength) {
                    // Try to extract string at this offset
                    $absOffset = $offset + $stringOffset;
                    if ($absOffset < $pageSize) {
                        list($str, $newOffset) = $this->pdbParser->extractString($pageData, $absOffset);
                        
                        if ($str && strlen(trim($str)) > 0) {
                            // Clean the string
                            $nullPos = strpos($str, "\x00");
                            if ($nullPos !== false) {
                                $str = substr($str, 0, $nullPos);
                            }
                            $str = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $str);
                            $result['data']['name'] = trim($str);
                        }
                    }
                }
            }
            
            // Extract additional numeric fields
            if ($maxLength >= 12) {
                for ($i = 0; $i < min(12, $maxLength); $i++) {
                    $result['data']['byte_' . $i] = ord($rowData[$i]);
                }
            }
            
            return $result;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getColumns() {
        return $this->columns;
    }
}
