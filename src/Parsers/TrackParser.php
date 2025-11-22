<?php

namespace RekordboxReader\Parsers;

class TrackParser {
    private $pdbParser;
    private $logger;
    private $tracks;
    private $artistAlbumParser;
    private $genreParser;
    private $keyParser;
    private $colorParser;
    private $labelParser;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->tracks = [];
        $this->artistAlbumParser = null;
        $this->genreParser = null;
        $this->keyParser = null;
        $this->colorParser = null;
        $this->labelParser = null;
    }

    public function setArtistAlbumParser($parser) {
        $this->artistAlbumParser = $parser;
    }

    public function setGenreParser($parser) {
        $this->genreParser = $parser;
    }

    public function setKeyParser($parser) {
        $this->keyParser = $parser;
    }

    public function setColorParser($parser) {
        $this->colorParser = $parser;
    }

    public function setLabelParser($parser) {
        $this->labelParser = $parser;
    }

    public function parseTracks() {
        $tracksTable = $this->pdbParser->getTable(PdbParser::TABLE_TRACKS);

        if (!$tracksTable) {
            if ($this->logger) {
                $this->logger->warning("Tracks table not found in database");
            }
            return [];
        }

        if ($this->logger) {
            $this->logger->info("Parsing tracks dari database...");
        }

        $this->tracks = $this->extractTrackRows($tracksTable);

        if ($this->logger) {
            $this->logger->info("Total " . count($this->tracks) . " tracks berhasil di-parse");
        }

        return $this->tracks;
    }

    private function extractTrackRows($table) {
        $tracks = [];
        
        $firstPage = $table['first_page'];
        $lastPage = $table['last_page'];
        $expectedType = $table['type'];

        $currentPage = $firstPage;
        $visitedPages = [];

        while ($currentPage > 0 && $currentPage <= $lastPage) {
            if (isset($visitedPages[$currentPage])) {
                if ($this->logger) {
                    $this->logger->warning("Circular reference detected at page {$currentPage}, stopping to prevent infinite loop");
                }
                break;
            }
            
            $visitedPages[$currentPage] = true;
            
            $pageData = $this->pdbParser->readPage($currentPage);
            if (!$pageData) {
                break;
            }

            $pageHeader = unpack(
                'Vgap/' .
                'Vpage_index/' .
                'Vtype/' .
                'Vnext_page',
                substr($pageData, 0, 16)
            );

            if ($pageHeader['type'] != $expectedType) {
                if ($this->logger) {
                    $this->logger->debug("Page {$currentPage} has type {$pageHeader['type']}, expected {$expectedType}, stopping");
                }
                break;
            }

            $trackRows = $this->parseTrackPage($pageData, $currentPage);
            $tracks = array_merge($tracks, $trackRows);

            $currentPage = $pageHeader['next_page'];
        }

        return $tracks;
    }

    private function parseTrackPage($pageData, $pageIdx) {
        $tracks = [];

        try {
            if (strlen($pageData) < 48) {
                return $tracks;
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
                'Cpage_flags/' .
                'vfree_size/' .
                'vused_size/' .
                'vu5/' .
                'vnum_rows_large',
                substr($pageData, 0, 36)
            );

            $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                if ($this->logger) {
                    $this->logger->debug("Page {$pageIdx} is not a data page, skipping");
                }
                return $tracks;
            }

            $numRows = $pageHeader['num_rows_large'];
            if ($numRows == 0 || $numRows == 0x1fff) {
                $numRows = $pageHeader['num_rows_small'];
            }

            if ($numRows == 0) {
                return $tracks;
            }

            $heapPos = 40;
            $pageSize = strlen($pageData);
            $numGroups = intval(($numRows - 1) / 16) + 1;
            
            $seenTrackIds = [];
            
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
                    
                    if ($actualRowOffset >= $pageSize || $actualRowOffset + 200 > $pageSize) {
                        continue;
                    }

                    $track = $this->parseTrackRow($pageData, $actualRowOffset);
                    if ($track && !in_array($track['id'], $seenTrackIds)) {
                        $tracks[] = $track;
                        $seenTrackIds[] = $track['id'];
                    }
                }
            }

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing track page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $tracks;
    }

    private function parseTrackRow($pageData, $offset) {
        try {
            if ($offset + 0x94 > strlen($pageData)) {
                return null;
            }
            
            $quickCheck = unpack('V', substr($pageData, $offset, 4));
            if ($quickCheck[1] == 0 || $quickCheck[1] == 0xFFFFFFFF) {
                return null;
            }

            // Parse according to Kaitai Struct rekordbox_pdb.ksy track_row definition
            $fixed = unpack(
                'vsubtype/' .          // 0x00 - Always 0x24
                'vindex_shift/' .      // 0x02
                'Vbitmask/' .          // 0x04
                'Vsample_rate/' .      // 0x08
                'Vcomposer_id/' .      // 0x0C
                'Vfile_size/' .        // 0x10
                'Vu1/' .               // 0x14
                'vu2/' .               // 0x18 - Always 19048?
                'vu3/' .               // 0x1A - Always 30967?
                'Vartwork_id/' .       // 0x1C
                'Vkey_id/' .           // 0x20 - Musical Key ID (32-bit)
                'Voriginal_artist_id/' . // 0x24
                'Vlabel_id/' .         // 0x28
                'Vremixer_id/' .       // 0x2C
                'Vbitrate/' .          // 0x30
                'Vtrack_number/' .     // 0x34
                'Vtempo/' .            // 0x38 - BPM * 100
                'Vgenre_id/' .         // 0x3C
                'Valbum_id/' .         // 0x40
                'Vartist_id/' .        // 0x44
                'Vid/' .               // 0x48 - Track ID
                'vdisc_number/' .      // 0x4C
                'vplay_count/' .       // 0x4E
                'vyear/' .             // 0x50
                'vsample_depth/' .     // 0x52
                'vduration/' .         // 0x54 - Duration in seconds
                'vu4/' .               // 0x56 - Always 41?
                'Ccolor_id/' .         // 0x58
                'Crating/' .           // 0x59
                'vu5/' .               // 0x5A - Always 1?
                'vu6',                 // 0x5C - Alternating 2 or 3
                substr($pageData, $offset, 0x5E)
            );

            // String offsets start at 0x5E
            $stringOffsets = [];
            $stringBase = $offset + 0x5E;
            
            for ($i = 0; $i < 21; $i++) {
                $strOffsetData = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
                $stringOffsets[] = $strOffsetData[1];
            }

            $strings = [];
            foreach ($stringOffsets as $idx => $strOffset) {
                if ($strOffset > 0) {
                    $absOffset = $offset + $strOffset;
                    if ($absOffset < strlen($pageData)) {
                        list($str, $newOffset) = $this->pdbParser->extractString($pageData, $absOffset);
                        
                        // Clean null bytes
                        $nullPos = strpos($str, "\x00");
                        if ($nullPos !== false) {
                            $str = substr($str, 0, $nullPos);
                        }
                        
                        // Remove control characters that interfere with parsing
                        $str = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $str);
                        
                        $strings[$idx] = trim($str);
                    } else {
                        $strings[$idx] = '';
                    }
                } else {
                    $strings[$idx] = '';
                }
            }

            // Extract title from string[17] (as per Kaitai spec)
            $title = '';
            
            // Try title field first (string index 17)
            if (!empty($strings[17])) {
                $title = $strings[17];
            }
            
            // Fallback: try filename from string[19]
            if (empty($title) && !empty($strings[19])) {
                $title = $strings[19];
                // Remove file extension
                $title = preg_replace('/\.(mp3|wav|flac|aac|m4a)$/i', '', $title);
            }
            
            // Fallback: try to extract from file path (string index 20)
            if (empty($title) && !empty($strings[20])) {
                if (preg_match('/([^\/]+)\.[^.]+$/', $strings[20], $matches)) {
                    $title = $matches[1];
                }
            }
            
            // Clean up title
            if (!empty($title)) {
                // Remove file extension if present
                $title = preg_replace('/\.(mp3|wav|flac|aac|m4a)$/i', '', $title);
            }
            
            if (empty($title)) {
                $title = 'Unknown Title';
            }

            // Parse Artist (return empty if not found, not "Unknown Artist")
            $artistName = '';
            if ($this->artistAlbumParser && isset($fixed['artist_id']) && $fixed['artist_id'] > 0) {
                $artistName = $this->artistAlbumParser->getArtistName($fixed['artist_id']);
            }
            
            // Parse Album
            $albumName = '';
            if ($this->artistAlbumParser && isset($fixed['album_id']) && $fixed['album_id'] > 0) {
                $albumName = $this->artistAlbumParser->getAlbumName($fixed['album_id']);
            }

            // Parse Genre (return empty if not found)
            $genreName = '';
            if ($this->genreParser && isset($fixed['genre_id']) && $fixed['genre_id'] > 0) {
                $genreName = $this->genreParser->getGenreName($fixed['genre_id']);
            }

            // Parse Musical Key
            $keyName = '';
            $keyId = $fixed['key_id'] ?? 0;
            if ($this->keyParser && $keyId > 0) {
                $keyName = $this->keyParser->getKeyName($keyId);
            }
            
            // BPM normalization: convert from tempo (BPM * 100) to integer BPM
            $bpm = isset($fixed['tempo']) && $fixed['tempo'] > 0 ? intval(round($fixed['tempo'] / 100.0)) : 0;
            
            // Extract analyze path (ANLZ file path, string index 14 as per Kaitai spec)
            $analyzePath = $strings[14] ?? '';
            
            // Extract file path (string index 20)
            $filePath = $strings[20] ?? '';

            return [
                'id' => $fixed['id'],
                'title' => trim($title),
                'artist' => trim($artistName),
                'album' => trim($albumName),
                'label' => '',
                'key' => trim($keyName),
                'key_id' => $keyId,
                'genre' => trim($genreName),
                'artist_id' => $fixed['artist_id'] ?? 0,
                'album_id' => $fixed['album_id'] ?? 0,
                'genre_id' => $fixed['genre_id'] ?? 0,
                'composer_id' => $fixed['composer_id'] ?? 0,
                'remixer_id' => $fixed['remixer_id'] ?? 0,
                'original_artist_id' => $fixed['original_artist_id'] ?? 0,
                'label_id' => $fixed['label_id'] ?? 0,
                'file_path' => trim($filePath),
                'analyze_path' => trim($analyzePath),
                'comment' => trim($strings[16] ?? ''),
                'duration' => $fixed['duration'] ?? 0,
                'bpm' => $bpm,
                'sample_rate' => $fixed['sample_rate'] ?? 0,
                'sample_depth' => $fixed['sample_depth'] ?? 0,
                'bitrate' => $fixed['bitrate'] ?? 0,
                'year' => $fixed['year'] ?? 0,
                'rating' => $fixed['rating'] ?? 0,
                'color_id' => $fixed['color_id'] ?? 0,
                'artwork_id' => $fixed['artwork_id'] ?? 0,
                'play_count' => $fixed['play_count'] ?? 0,
                'track_number' => $fixed['track_number'] ?? 0,
                'disc_number' => $fixed['disc_number'] ?? 0,
                'file_size' => $fixed['file_size'] ?? 0
            ];

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing track row at offset {$offset}: " . $e->getMessage());
            }
            return null;
        }
    }

    public function getTrackById($trackId) {
        foreach ($this->tracks as $track) {
            if ($track['id'] == $trackId) {
                return $track;
            }
        }
        return null;
    }

    public function getTracks() {
        return $this->tracks;
    }
}
