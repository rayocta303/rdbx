<?php

namespace RekordboxReader\Parsers;

class TrackParser {
    private $pdbParser;
    private $logger;
    private $tracks;
    private $artistAlbumParser;
    private $genreParser;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->tracks = [];
        $this->artistAlbumParser = null;
        $this->genreParser = null;
    }

    public function setArtistAlbumParser($parser) {
        $this->artistAlbumParser = $parser;
    }

    public function setGenreParser($parser) {
        $this->genreParser = $parser;
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

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $trackRows = $this->parseTrackPage($pageData, $pageIdx);
            $tracks = array_merge($tracks, $trackRows);
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
            
            $rowIndexStart = $pageSize - 4 - (2 * $numRows);
            
            for ($rowIdx = 0; $rowIdx < $numRows; $rowIdx++) {
                $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
                
                if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                    continue;
                }
                
                $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
                $rowOffset = $rowOffsetData[1];
                
                $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
                
                if ($actualRowOffset >= $pageSize || $actualRowOffset + 200 > $pageSize) {
                    if ($this->logger) {
                        $this->logger->debug("Skipping row $rowIdx: invalid offset $actualRowOffset");
                    }
                    continue;
                }

                $track = $this->parseTrackRow($pageData, $actualRowOffset);
                if ($track) {
                    $tracks[] = $track;
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

            $fixed = unpack(
                'Vu1/' .           // 0x00
                'Vu2/' .           // 0x04
                'Vsample_rate/' .  // 0x08
                'Vu3/' .           // 0x0C
                'Vfile_size/' .    // 0x10
                'Vu4/' .           // 0x14
                'Vu5/' .           // 0x18
                'Vu6/' .           // 0x1C
                'Vu7/' .           // 0x20
                'Vu8/' .           // 0x24
                'Vu9/' .           // 0x28
                'vu10/' .          // 0x2C
                'vu11/' .          // 0x2E
                'vbitrate/' .      // 0x30
                'vu12/' .          // 0x32
                'vu13/' .          // 0x34
                'vu14/' .          // 0x36
                'vtempo/' .        // 0x38 - BPM * 100
                'vu15/' .          // 0x3A
                'vu16/' .          // 0x3C
                'vu17/' .          // 0x3E
                'vgenre_id/' .     // 0x40
                'valbum_id/' .     // 0x42
                'vartist_id/' .    // 0x44
                'vid/' .           // 0x46
                'vplay_count/' .   // 0x48
                'vu18/' .          // 0x4A
                'vyear/' .         // 0x4C
                'vsample_depth/' . // 0x4E
                'vu19/' .          // 0x50
                'vduration/' .     // 0x52
                'vu20/' .          // 0x54
                'vu21/' .          // 0x56
                'Ccolor_id/' .     // 0x58
                'Crating/' .       // 0x59
                'vu22/' .          // 0x5A
                'vu23',            // 0x5C
                substr($pageData, $offset, 0x5E)
            );

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
                        
                        $nullPos = strpos($str, "\x00");
                        if ($nullPos !== false) {
                            $str = substr($str, 0, $nullPos);
                        }
                        
                        if (strpos($str, ';') !== false) {
                            $parts = explode(';', $str);
                            $str = $parts[0];
                        }
                        
                        $strings[$idx] = trim($str);
                    } else {
                        $strings[$idx] = '';
                    }
                } else {
                    $strings[$idx] = '';
                }
            }

            $title = $strings[17] ?? 'Unknown Title';
            if (strpos($title, ';') !== false) {
                $parts = explode(';', $title);
                $title = trim($parts[0]);
            }
            if (strpos($title, '.') !== false && substr($title, -4) !== '.mp3') {
                $title = substr($title, 0, strrpos($title, '.'));
            }

            $artistName = 'Unknown Artist';
            $albumName = '';
            
            if ($this->artistAlbumParser && isset($fixed['artist_id'])) {
                $artistName = $this->artistAlbumParser->getArtistName($fixed['artist_id']);
            } elseif (isset($fixed['artist_id'])) {
                $artistName = "Artist #{$fixed['artist_id']}";
            }
            
            if ($this->artistAlbumParser && isset($fixed['album_id'])) {
                $albumName = $this->artistAlbumParser->getAlbumName($fixed['album_id']);
            } elseif (isset($fixed['album_id'])) {
                $albumName = "Album #{$fixed['album_id']}";
            }

            $genreName = '';
            if ($this->genreParser && isset($fixed['genre_id'])) {
                $genreName = $this->genreParser->getGenreName($fixed['genre_id']);
            } elseif (isset($fixed['genre_id']) && $fixed['genre_id'] > 0) {
                $genreName = "Genre #{$fixed['genre_id']}";
            }

            return [
                'id' => $fixed['id'],
                'title' => $title,
                'artist' => $artistName,
                'album' => $albumName,
                'label' => isset($fixed['label_id']) ? "Label #{$fixed['label_id']}" : '',
                'key' => isset($fixed['key_id']) ? "Key #{$fixed['key_id']}" : '',
                'genre' => $genreName,
                'artist_id' => $fixed['artist_id'] ?? 0,
                'album_id' => $fixed['album_id'] ?? 0,
                'genre_id' => $fixed['genre_id'] ?? 0,
                'file_path' => $strings[1] ?? '',
                'analyze_path' => $strings[14] ?? '',
                'comment' => $strings[16] ?? '',
                'duration' => $fixed['duration'] ?? 0,
                'bpm' => isset($fixed['tempo']) ? round($fixed['tempo'] / 100.0, 2) : 0,
                'sample_rate' => $fixed['sample_rate'] ?? 0,
                'bitrate' => $fixed['bitrate'] ?? 0,
                'year' => $fixed['year'] ?? 0,
                'rating' => $fixed['rating'] ?? 0,
                'color_id' => $fixed['color_id'] ?? 0,
                'artwork_id' => $fixed['artwork_id'] ?? 0,
                'play_count' => $fixed['play_count'] ?? 0,
                'track_number' => $fixed['track_number'] ?? 0,
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
