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
            
            // According to rekordcrate spec:
            // ColumnEntry row structure:
            // 0x00 - 0x01: subtype (uint16) - Always 0x06
            // 0x02 - 0x03: index_shift (uint16)
            // 0x04 - 0x05: unknown field
            
            $rowData = substr($pageData, $offset, $maxLength);
            $hexDump = bin2hex($rowData);
            
            $result = [
                'offset' => $offset,
                'raw_hex' => substr($hexDump, 0, 128),
                'data' => []
            ];
            
            // Parse header (first 6 bytes)
            if ($maxLength >= 6) {
                $header = unpack(
                    'vsubtype/' .      // 0x00
                    'vindex_shift/' .  // 0x02
                    'vunknown',        // 0x04
                    substr($rowData, 0, 6)
                );
                
                $result['data']['subtype'] = $header['subtype'];
                $result['data']['index_shift'] = $header['index_shift'];
                $result['data']['id'] = $header['index_shift']; // Use index_shift as ID
                
                // Classify column type based on observed patterns
                $result['data']['column_type'] = $this->getColumnTypeName($header['index_shift']);
            }
            
            // Try to extract string data
            // String offset typically at position 4 or 6
            if ($maxLength >= 8) {
                $stringOffsetRaw = unpack('v', substr($rowData, 6, 2))[1] ?? 0;
                // Mask out high-bit flag (like in other parsers)
                $stringOffset = $stringOffsetRaw & 0x1FFF;
                
                if ($stringOffset > 0 && $stringOffset < $maxLength) {
                    $absOffset = $offset + $stringOffset;
                    if ($absOffset < $pageSize) {
                        list($str, $newOffset) = $this->pdbParser->extractString($pageData, $absOffset);
                        
                        if ($str && strlen(trim($str)) > 0) {
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
            
            return $result;

        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getColumnTypeName($indexShift) {
        // Based on rekordcrate documentation and observed patterns
        // These are the browsing categories used by CDJs
        $columnTypes = [
            0x00 => 'Track',
            0x01 => 'Genre',
            0x02 => 'Artist',
            0x03 => 'Album',
            0x04 => 'Label',
            0x05 => 'Key',
            0x06 => 'Rating',
            0x07 => 'Color',
            0x08 => 'Time',
            0x09 => 'Bit Rate',
            0x0A => 'BPM',
            0x0B => 'Year',
            0x0C => 'Comment',
            0x0D => 'Date Added',
            0x0E => 'Original Artist',
            0x0F => 'Remixer',
            0x10 => 'Composer',
            0x11 => 'Album Artist',
            0x12 => 'DJ Play Count'
        ];
        
        return $columnTypes[$indexShift] ?? 'Unknown_' . dechex($indexShift);
    }

    public function getColumns() {
        return $this->columns;
    }
}
