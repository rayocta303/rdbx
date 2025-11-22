<?php

namespace RekordboxReader\Parsers;

class LabelParser {
    private $pdbParser;
    private $logger;
    private $labels;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->labels = [];
    }

    public function parseLabels() {
        $labelsTable = $this->pdbParser->getTable(PdbParser::TABLE_LABELS);
        
        if (!$labelsTable) {
            if ($this->logger) {
                $this->logger->warning("Labels table not found in database");
            }
            return [];
        }

        $labels = $this->extractRows($labelsTable);
        
        $this->labels = [];
        foreach ($labels as $label) {
            $this->labels[$label['id']] = $label['name'];
        }

        if ($this->logger) {
            $this->logger->info("Parsed " . count($this->labels) . " labels from database");
        }

        return $this->labels;
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
                $this->logger->debug("Error parsing label page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parseRow($pageData, $offset) {
        try {
            if ($offset + 10 > strlen($pageData)) {
                return null;
            }
            
            $id = unpack('V', substr($pageData, $offset, 4))[1];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            $nameOffset = $offset + 4;
            for ($scan = $nameOffset; $scan < $offset + 20 && $scan < strlen($pageData); $scan++) {
                $byte = ord($pageData[$scan]);
                if ($byte == 0x40 || $byte == 0x90 || ($byte > 0 && $byte < 0x80 && ($byte & 1))) {
                    list($name, $newOffset) = $this->pdbParser->extractString($pageData, $scan);
                    if ($name && strlen($name) > 0) {
                        return ['id' => $id, 'name' => trim($name)];
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getLabelName($labelId) {
        return $this->labels[$labelId] ?? "";
    }
}
