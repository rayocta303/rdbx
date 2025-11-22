<?php

namespace RekordboxReader\Parsers;

class ArtworkParser {
    private $pdbParser;
    private $logger;
    private $artworks;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->artworks = [];
    }

    public function parseArtwork() {
        $artworkTable = $this->pdbParser->getTable(PdbParser::TABLE_ARTWORK);
        
        if (!$artworkTable) {
            if ($this->logger) {
                $this->logger->warning("Artwork table not found in database");
            }
            return [];
        }

        $artworks = $this->extractRows($artworkTable);
        
        $this->artworks = [];
        foreach ($artworks as $artwork) {
            $this->artworks[$artwork['id']] = $artwork['path'];
        }

        if ($this->logger) {
            $this->logger->info("Parsed " . count($this->artworks) . " artworks from database");
        }

        return $this->artworks;
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
                $this->logger->debug("Error parsing artwork page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parseRow($pageData, $offset) {
        try {
            if ($offset + 12 > strlen($pageData)) {
                return null;
            }
            
            // According to rekordcrate spec:
            // Artwork row structure:
            // 0x00 - 0x01: subtype (uint16) - Always 0x03
            // 0x02 - 0x03: index_shift (uint16)
            // 0x04 - 0x07: artwork_id (uint32)
            // 0x08+: path string offset
            
            $header = unpack(
                'vsubtype/' .      // 0x00
                'vindex_shift/' .  // 0x02
                'Vartwork_id',     // 0x04
                substr($pageData, $offset, 8)
            );
            
            if ($header['subtype'] != 0x03) {
                // Not an artwork row
                return null;
            }
            
            $id = $header['artwork_id'];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            // Extract artwork path string
            list($path, $newOffset) = $this->pdbParser->extractString($pageData, $offset + 8);
            
            if ($path && strlen($path) > 0) {
                // Clean path
                $nullPos = strpos($path, "\x00");
                if ($nullPos !== false) {
                    $path = substr($path, 0, $nullPos);
                }
                $path = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $path);
                
                return [
                    'id' => $id,
                    'path' => trim($path),
                    'subtype' => $header['subtype'],
                    'index_shift' => $header['index_shift']
                ];
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getArtworkPath($artworkId) {
        return $this->artworks[$artworkId] ?? "";
    }
}
