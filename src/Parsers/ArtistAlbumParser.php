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
            if ($offset + 10 > strlen($pageData)) {
                return null;
            }
            
            // Artist/Album row structure per Kaitai spec:
            // 0x00: subtype (u2) - 0x60/0x80 = short name, 0x64/0x84 = far string heap
            // 0x02: index_shift (u2)
            // 0x04: id (u4)
            // 0x08: u1 (always 0x03)
            // 0x09: ofs_name_near (u1) - offset from row start
            // 0x0a: ofs_name_far (u2) - if subtype & 0x04 or subtype & 0x80
            
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
            
            // Determine name offset using far-string semantics
            $nameOffset = 0;
            if (($fixed['subtype'] & 0x04) == 0x04 || ($fixed['subtype'] & 0x80) == 0x80) {
                // Far string: read u2 at offset+0x0a, mask with 0x1FFF, add heapPos
                if ($offset + 12 > strlen($pageData)) {
                    return null;
                }
                $ofsData = unpack('v', substr($pageData, $offset + 0x0a, 2));
                $nameOffset = ($ofsData[1] & 0x1FFF) + $heapPos;
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

    public function getArtistName($artistId) {
        return $this->artists[$artistId] ?? "Unknown Artist";
    }

    public function getAlbumName($albumId) {
        return $this->albums[$albumId] ?? "";
    }
}
