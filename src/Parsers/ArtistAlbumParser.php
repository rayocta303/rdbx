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
        foreach ($artists as $artist) {
            $this->artists[$artist['id']] = $artist['name'];
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
        foreach ($albums as $album) {
            $this->albums[$album['id']] = $album['name'];
        }

        return $this->albums;
    }

    private function extractRows($table, $type) {
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
            
            $pageRows = $this->parsePage($pageData, $currentPageIdx, $type);
            $rows = array_merge($rows, $pageRows);
            
            if ($currentPageIdx == $lastPage) {
                break;
            }
            
            $currentPageIdx = $pageHeader['next_page'];
            $iteration++;
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
                    // Check presence bit for this row
                    $presenceBit = 1 << $rowIdx;
                    if (($presenceFlags & $presenceBit) == 0) {
                        // Row not present, skip
                        continue;
                    }
                    
                    $rowOffsetPos = $base - (6 + ($rowIdx * 2));
                    
                    if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                        continue;
                    }
                    
                    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                    $rowOffset = $rowOffsetData[1];
                    
                    if ($rowOffset == 0) {
                        // Invalid offset, skip
                        continue;
                    }
                    
                    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                    
                    if ($actualRowOffset >= $pageSize || $actualRowOffset + 20 > $pageSize) {
                        continue;
                    }

                    $row = $this->parseRow($pageData, $actualRowOffset, $type, $heapPos);
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

    private function parseRow($pageData, $offset, $type, $heapPos) {
        try {
            if ($type === 'artist') {
                return $this->parseArtistRow($pageData, $offset, $heapPos);
            } else {
                return $this->parseAlbumRow($pageData, $offset, $heapPos);
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function parseArtistRow($pageData, $offset, $heapPos) {
        try {
            if ($offset + 10 > strlen($pageData)) {
                return null;
            }
            
            // Artist row structure per Kaitai spec:
            // 0x00: subtype (u2) - 0x60 or 0x64
            // 0x02: index_shift (u2)
            // 0x04: id (u4)
            // 0x08: u1 (always 0x03)
            // 0x09: ofs_name_near (u1)
            // 0x0a: ofs_name_far (u2) - only if subtype == 0x64
            
            $fixed = unpack(
                'vsubtype/' .      // 0x00
                'vindex_shift/' .  // 0x02
                'Vid/' .           // 0x04
                'Cu1/' .           // 0x08
                'Cofs_name_near',  // 0x09
                substr($pageData, $offset, 10)
            );
            
            $id = $fixed['id'];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            // Determine name offset based on subtype
            $nameOffset = 0;
            if ($fixed['subtype'] == 0x64) {
                // Far string: read u2 at offset+0x0a
                if ($offset + 12 > strlen($pageData)) {
                    return null;
                }
                $ofsData = unpack('v', substr($pageData, $offset + 0x0a, 2));
                $nameOffset = $heapPos + $ofsData[1];
            } else {
                // Near offset: use ofs_name_near relative to row start
                $nameOffset = $offset + $fixed['ofs_name_near'];
            }
            
            // Extract name string
            if ($nameOffset >= strlen($pageData)) {
                return null;
            }
            
            list($name, $newOffset) = $this->pdbParser->extractString($pageData, $nameOffset);
            
            if ($name && strlen(trim($name)) > 0 && $id > 0) {
                return ['id' => $id, 'name' => trim($name)];
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function parseAlbumRow($pageData, $offset, $heapPos) {
        try {
            if ($offset + 22 > strlen($pageData)) {
                return null;
            }
            
            // Album row structure per Kaitai spec:
            // 0x00: u2 (magic word)
            // 0x02: index_shift (u2)
            // 0x04: u4
            // 0x08: artist_id (u4)
            // 0x0c: id (u4)
            // 0x10: u4
            // 0x14: u1 (0x03)
            // 0x15: ofs_name (u1)
            
            $fixed = unpack(
                'vmagic/' .        // 0x00
                'vindex_shift/' .  // 0x02
                'Vu1/' .           // 0x04
                'Vartist_id/' .    // 0x08
                'Vid/' .           // 0x0c
                'Vu2/' .           // 0x10
                'Cu3/' .           // 0x14
                'Cofs_name',       // 0x15
                substr($pageData, $offset, 22)
            );
            
            $id = $fixed['id'];
            
            if ($id == 0 || $id > 100000) {
                return null;
            }
            
            // Name offset is relative to row start
            $nameOffset = $offset + $fixed['ofs_name'];
            
            // Extract name string
            if ($nameOffset >= strlen($pageData)) {
                return null;
            }
            
            list($name, $newOffset) = $this->pdbParser->extractString($pageData, $nameOffset);
            
            if ($name && strlen(trim($name)) > 0 && $id > 0) {
                return ['id' => $id, 'name' => trim($name)];
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
