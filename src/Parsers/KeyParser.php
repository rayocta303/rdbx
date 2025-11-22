<?php

namespace RekordboxReader\Parsers;

class KeyParser {
    private $pdbParser;
    private $logger;
    private $keys;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->keys = [];
    }

    public function parseKeys() {
        $keysTable = $this->pdbParser->getTable(PdbParser::TABLE_KEYS);
        
        if (!$keysTable) {
            if ($this->logger) {
                $this->logger->warning("Keys table not found in database");
            }
            return [];
        }

        $keys = $this->extractRows($keysTable);
        
        $this->keys = [];
        foreach ($keys as $key) {
            $this->keys[$key['id']] = $key['name'];
        }

        if ($this->logger) {
            $this->logger->info("Parsed " . count($this->keys) . " keys from database");
        }

        return $this->keys;
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
            
            $pageRows = $this->parsePage($pageData);
            $rows = array_merge($rows, $pageRows);
            
            if ($currentPageIdx == $lastPage) {
                break;
            }
            
            $currentPageIdx = $pageHeader['next_page'];
            $iteration++;
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
                    
                    $present = (($presenceFlags >> $rowIdx) & 1) != 0;
                    if (!$present) {
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
            if ($this->logger) {
                $this->logger->debug("Error parsing keys page: " . $e->getMessage());
            }
            return $rows;
        }

        return $rows;
    }

    private function parseRow($pageData, $offset) {
        try {
            if ($offset + 12 > strlen($pageData)) {
                return null;
            }
            
            // Key row structure per Kaitai spec:
            // 0x00: id (u4)
            // 0x04: id2 (u4) - seems to be a second copy of the ID
            // 0x08: name (device_sql_string)
            
            $id = unpack('V', substr($pageData, $offset, 4))[1];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            // Extract name at offset+8
            list($name, $newOffset) = $this->pdbParser->extractString($pageData, $offset + 8);
            
            if ($name && strlen(trim($name)) > 0 && $id > 0) {
                return ['id' => $id, 'name' => trim($name)];
            }

            return null;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing key row: " . $e->getMessage());
            }
            return null;
        }
    }

    public function getKeyName($keyId) {
        return $this->keys[$keyId] ?? "";
    }

    public function getKeys() {
        return $this->keys;
    }
}
