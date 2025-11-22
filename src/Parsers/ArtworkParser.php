<?php

namespace RekordboxReader\Parsers;

class ArtworkParser {
    private $pdbParser;
    private $logger;
    private $artwork;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->artwork = [];
    }

    public function parseArtwork() {
        $artworkTable = $this->pdbParser->getTable(PdbParser::TABLE_ARTWORK);
        
        if (!$artworkTable) {
            return [];
        }

        $artwork = $this->extractRows($artworkTable);
        
        $this->artwork = [];
        foreach ($artwork as $art) {
            $this->artwork[$art['id']] = $art['path'];
        }

        return $this->artwork;
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
                
                if ($flagsOffset < 0 || $flagsOffset + 4 > $pageSize) {
                    break;
                }
                
                $rowFlags = unpack('V', substr($pageData, $flagsOffset, 4))[1];
                
                for ($rowIdx = 0; $rowIdx < 16; $rowIdx++) {
                    $isPresent = ($rowFlags & (1 << $rowIdx)) != 0;
                    if (!$isPresent) {
                        continue;
                    }
                    
                    $offsetPos = $base - 6 - ($rowIdx * 2);
                    if ($offsetPos < 0 || $offsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffset = unpack('v', substr($pageData, $offsetPos, 2))[1];
                    
                    if ($rowOffset >= $pageSize || $rowOffset + 10 > $pageSize) {
                        continue;
                    }
                    
                    $rowData = unpack('vid', substr($pageData, $rowOffset, 2));
                    list($path, $newOff) = $this->pdbParser->extractString($pageData, $rowOffset + 4);
                    
                    if ($rowData['id'] > 0) {
                        $rows[] = [
                            'id' => $rowData['id'],
                            'path' => $path
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->warning("Error parsing artwork page: " . $e->getMessage());
            }
        }

        return $rows;
    }

    public function getArtwork() {
        return $this->artwork;
    }
}
