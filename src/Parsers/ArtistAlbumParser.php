<?php

namespace RekordboxReader\Parsers;

class ArtistAlbumParser {
    private $pdbParser;
    private $logger;
    private $artists;
    private $albums;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->artists = [];
        $this->albums = [];
    }

    public function parseArtists() {
        $artistsTable = $this->pdbParser->getTable(PdbParser::TABLE_ARTISTS);
        
        if (!$artistsTable) {
            return [];
        }

        $artists = $this->extractRows($artistsTable, 'artist');
        
        $this->artists = [];
        $index = 1;
        foreach ($artists as $artist) {
            $this->artists[$index] = $artist['name'];
            $this->artists[$artist['id']] = $artist['name'];
            $index++;
        }

        return $this->artists;
    }

    public function parseAlbums() {
        $albumsTable = $this->pdbParser->getTable(PdbParser::TABLE_ALBUMS);
        
        if (!$albumsTable) {
            return [];
        }

        $albums = $this->extractRows($albumsTable, 'album');
        
        $this->albums = [];
        $index = 1;
        foreach ($albums as $album) {
            $this->albums[$index] = $album['name'];
            $this->albums[$album['id']] = $album['name'];
            $index++;
        }

        return $this->albums;
    }

    private function extractRows($table, $type) {
        $rows = [];
        
        $firstPage = $table['first_page'];
        $lastPage = $table['last_page'];

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $pageRows = $this->parsePage($pageData, $pageIdx, $type);
            $rows = array_merge($rows, $pageRows);
        }

        return $rows;
    }

    private function parsePage($pageData, $pageIdx, $type) {
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
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset >= $pageSize || $actualRowOffset + 20 > $pageSize) {
                        continue;
                    }

                    $row = $this->parseRow($pageData, $actualRowOffset, $type);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
            }

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing {$type} page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $rows;
    }

    private function parseRow($pageData, $offset, $type) {
        try {
            if ($offset + 10 > strlen($pageData)) {
                return null;
            }
            
            $id = unpack('v', substr($pageData, $offset, 2))[1];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            $name = '';
            
            for ($scan = $offset + 4; $scan < min($offset + 100, strlen($pageData)); $scan++) {
                if ($scan >= strlen($pageData)) break;
                
                $byte = ord($pageData[$scan]);
                
                if (($byte & 0x01) == 1) {
                    list($str, $newOffset) = $this->pdbParser->extractString($pageData, $scan);
                    if ($str && strlen(trim($str)) > 0) {
                        $name = trim($str);
                        break;
                    }
                }
                elseif ($byte == 0x40 || $byte == 0x90) {
                    list($str, $newOffset) = $this->pdbParser->extractString($pageData, $scan);
                    if ($str && strlen(trim($str)) > 0) {
                        $name = trim($str);
                        break;
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

    public function getArtistName($artistId) {
        return $this->artists[$artistId] ?? "Unknown Artist";
    }

    public function getAlbumName($albumId) {
        return $this->albums[$albumId] ?? "";
    }
}
