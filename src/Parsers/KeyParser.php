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
            $id = unpack('v', substr($pageData, $offset, 2))[1];
            
            $name = '';
            
            for ($scan = $offset + 2; $scan < $offset + 150; $scan++) {
                if ($scan >= strlen($pageData)) break;
                
                $flags = ord($pageData[$scan]);
                if (($flags & 0x40) == 0) {
                    $len = $flags & 0x7F;
                    if ($len >= 1 && $len < 100 && ($scan + $len + 1) <= strlen($pageData)) {
                        $str = substr($pageData, $scan + 1, $len);
                        
                        $nullPos = strpos($str, "\x00");
                        if ($nullPos !== false) {
                            $str = substr($str, 0, $nullPos);
                        }
                        
                        $str = trim($str);
                        
                        if (strlen($str) >= 1 && preg_match('/^[0-9]{1,2}[ABd]$/i', $str)) {
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
