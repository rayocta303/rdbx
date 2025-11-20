<?php

namespace RekordboxReader\Parsers;

class TrackParser {
    private $pdbParser;
    private $logger;
    private $tracks;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->tracks = [];
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
                return $tracks;
            }

            $numRows = max($pageHeader['num_rows_small'], 
                          ($pageHeader['num_rows_large'] != 0x1fff) ? $pageHeader['num_rows_large'] : 0);

            if ($numRows == 0) {
                return $tracks;
            }

            $pageSize = strlen($pageData);
            $rowIndexOffset = $pageSize - ($numRows * 2);

            for ($i = 0; $i < $numRows; $i++) {
                $rowOffsetData = unpack('v', substr($pageData, $rowIndexOffset + ($i * 2), 2));
                $rowOffset = $rowOffsetData[1];

                $rowPresent = ($rowOffset & 0x8000) != 0;
                
                if (!$rowPresent) {
                    continue;
                }

                $actualRowOffset = ($rowOffset & 0x1FFF);
                
                if ($actualRowOffset + 20 > $pageSize) {
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
            $trackData = unpack(
                'vid/' .
                'vsample_depth/' .
                'vsample_rate/' .
                'Vduration/' .
                'Vunknown1/' .
                'vunknown2/' .
                'vunknown3/' .
                'vunknown4/' .
                'vunknown5/' .
                'vartwork_id/' .
                'vkey_id/' .
                'voriginal_artist_id/' .
                'vlabel_id/' .
                'vremixer_id/' .
                'vbitrate/' .
                'vrating/' .
                'vunknown6/' .
                'vunknown7/' .
                'vtempo/' .
                'vgenre_id/' .
                'valbum_id/' .
                'vartist_id/' .
                'vid2/' .
                'vdiscnum/' .
                'vplay_count/' .
                'vyear/' .
                'vsample_depth2/' .
                'vunknown8/' .
                'Vcolor_id/' .
                'vunknown9/' .
                'vunknown10',
                substr($pageData, $offset, 84)
            );

            $stringOffsets = [];
            for ($i = 0; $i < 21; $i++) {
                $offsetData = unpack('v', substr($pageData, $offset + 84 + ($i * 2), 2));
                $stringOffsets[] = $offsetData[1];
            }

            $strings = [];
            foreach ($stringOffsets as $strOffset) {
                if ($strOffset > 0 && ($offset + $strOffset) < strlen($pageData)) {
                    list($str, $newOffset) = $this->pdbParser->extractString($pageData, $offset + $strOffset);
                    $strings[] = $str;
                } else {
                    $strings[] = '';
                }
            }

            return [
                'id' => $trackData['id'],
                'title' => $strings[1] ?? 'Unknown Title',
                'artist' => $strings[4] ?? 'Unknown Artist',
                'album' => $strings[9] ?? '',
                'label' => $strings[7] ?? '',
                'key' => $strings[11] ?? '',
                'genre' => $strings[5] ?? '',
                'file_path' => $strings[20] ?? '',
                'duration' => $trackData['duration'],
                'bpm' => isset($trackData['tempo']) ? $trackData['tempo'] / 100.0 : 0,
                'sample_rate' => $trackData['sample_rate'],
                'bitrate' => $trackData['bitrate'],
                'year' => $trackData['year'],
                'rating' => $trackData['rating'],
                'color_id' => $trackData['color_id'],
                'artwork_id' => $trackData['artwork_id'],
                'play_count' => $trackData['play_count']
            ];

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error parsing track row: " . $e->getMessage());
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
