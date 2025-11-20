<?php

namespace RekordboxReader\Parsers;

class AnlzParser {
    const TAG_PPTH = 'PPTH';
    const TAG_PVBR = 'PVBR';
    const TAG_PWAV = 'PWAV';
    const TAG_PWV2 = 'PWV2';
    const TAG_PWV3 = 'PWV3';
    const TAG_PWV4 = 'PWV4';
    const TAG_PWV5 = 'PWV5';
    const TAG_PCOB = 'PCOB';
    const TAG_PQTZ = 'PQTZ';
    const TAG_PSSI = 'PSSI';

    private $anlzPath;
    private $logger;
    private $data;
    private $header;
    private $sections;

    public function __construct($anlzPath, $logger = null) {
        $this->anlzPath = $anlzPath;
        $this->logger = $logger;
        $this->data = '';
        $this->header = [];
        $this->sections = [];
    }

    public function parse() {
        if (!file_exists($this->anlzPath)) {
            if ($this->logger) {
                $this->logger->warning("ANLZ file not found: " . $this->anlzPath);
            }
            return [];
        }

        $this->data = file_get_contents($this->anlzPath);

        if ($this->logger) {
            $this->logger->debug("Parsing ANLZ file: " . basename($this->anlzPath));
        }

        $this->header = $this->parseHeader();
        $this->parseSections();

        return [
            'header' => $this->header,
            'sections' => array_keys($this->sections),
            'beat_grid' => $this->extractBeatgrid(),
            'waveform' => $this->extractWaveform(),
            'cue_points' => $this->extractCuePoints()
        ];
    }

    private function parseHeader() {
        if (strlen($this->data) < 28) {
            return [];
        }

        $fourcc = substr($this->data, 0, 4);
        if ($fourcc != 'PMAI') {
            if ($this->logger) {
                $this->logger->warning("Invalid ANLZ signature: " . $fourcc);
            }
            return [];
        }

        $headerData = unpack(
            'Nlen_header/' .
            'Nlen_file',
            substr($this->data, 4, 8)
        );

        return [
            'fourcc' => $fourcc,
            'len_header' => $headerData['len_header'],
            'len_file' => $headerData['len_file']
        ];
    }

    private function parseSections() {
        if (empty($this->header)) {
            return;
        }

        $offset = $this->header['len_header'] ?? 28;

        while ($offset + 12 <= strlen($this->data)) {
            $tag = substr($this->data, $offset, 4);

            if ($tag[0] != 'P') {
                break;
            }

            $sectionLenData = unpack('N', substr($this->data, $offset + 8, 4));
            $sectionLen = $sectionLenData[1];

            if ($offset + $sectionLen > strlen($this->data)) {
                break;
            }

            $sectionData = substr($this->data, $offset, $sectionLen);

            if (!isset($this->sections[$tag])) {
                $this->sections[$tag] = [];
            }
            $this->sections[$tag][] = $sectionData;

            $offset += $sectionLen;
        }
    }

    private function extractBeatgrid() {
        $beatgrid = [];

        if ($this->logger) {
            $this->logger->debug("Beatgrid extracted: " . count($beatgrid) . " beats");
        }

        return $beatgrid;
    }

    private function extractWaveform() {
        $waveform = [
            'preview' => null,
            'detail' => null,
            'color' => null,
            'preview_data' => null,
            'color_data' => null
        ];

        // Parse preview waveform (PWAV)
        if (isset($this->sections['PWAV'])) {
            $waveData = $this->parseWaveformData($this->sections['PWAV'][0], false);
            $waveform['preview'] = [
                'type' => 'monochrome',
                'data_length' => strlen($this->sections['PWAV'][0]),
                'samples' => count($waveData)
            ];
            $waveform['preview_data'] = $waveData;
        }

        // Parse detailed waveform (PWV3)
        if (isset($this->sections['PWV3'])) {
            $waveform['detail'] = [
                'type' => 'monochrome',
                'data_length' => strlen($this->sections['PWV3'][0])
            ];
        }

        // Parse color waveform (PWV5)
        if (isset($this->sections['PWV5'])) {
            $waveData = $this->parseWaveformData($this->sections['PWV5'][0], true);
            $waveform['color'] = [
                'type' => 'color',
                'data_length' => strlen($this->sections['PWV5'][0]),
                'samples' => count($waveData)
            ];
            $waveform['color_data'] = $waveData;
        }

        return $waveform;
    }
    
    private function parseWaveformData($sectionData, $isColor) {
        $waveData = [];
        
        if (strlen($sectionData) < 20) {
            return $waveData;
        }
        
        // Skip section header (20 bytes)
        $offset = 20;
        
        // Each waveform sample is 1 byte for mono, or 3-6 bytes for color
        $sampleSize = $isColor ? 6 : 1;
        
        while ($offset + $sampleSize <= strlen($sectionData)) {
            if ($isColor) {
                // Color waveform: RGB values for different frequency bands
                $sample = unpack('Cred/Cgreen/Cblue/Cred2/Cgreen2/Cblue2', substr($sectionData, $offset, 6));
                $waveData[] = [
                    'height' => max($sample['red'], $sample['green'], $sample['blue']),
                    'r' => $sample['red'],
                    'g' => $sample['green'],
                    'b' => $sample['blue']
                ];
            } else {
                // Monochrome waveform: single height value
                $height = ord($sectionData[$offset]);
                $waveData[] = ['height' => $height];
            }
            
            $offset += $sampleSize;
        }
        
        return $waveData;
    }

    private function extractCuePoints() {
        $cuePoints = [];

        // Try extended (nxs2) cue list first (PCO2)
        if (isset($this->sections['PCO2'])) {
            $cuePoints = $this->parsePCO2Section($this->sections['PCO2'][0]);
        }
        // Fall back to standard cue list (PCOB)
        elseif (isset($this->sections['PCOB'])) {
            $cuePoints = $this->parsePCOBSection($this->sections['PCOB'][0]);
        }

        return $cuePoints;
    }
    
    private function parsePCOBSection($sectionData) {
        $cues = [];
        
        if (strlen($sectionData) < 24) {
            return $cues;
        }
        
        $header = unpack(
            'Nlen_header/' .
            'Nlen_tag/' .
            'Vtype/' .
            'vunk/' .
            'vlencues/' .
            'Vmemory_count',
            substr($sectionData, 4, 20)
        );
        
        $numCues = $header['lencues'] ?? 0;
        $offset = 24;
        
        for ($i = 0; $i < $numCues && $offset + 56 <= strlen($sectionData); $i++) {
            $cueData = unpack(
                'Nhot_cue/' .
                'Vstatus/' .
                'Vunknown1/' .
                'vorder_first/' .
                'vorder_last/' .
                'Ctype/' .
                'Cu1/' .
                'vu2/' .
                'Vtime/' .
                'Vloop_time',
                substr($sectionData, $offset + 12, 28)
            );
            
            $cues[] = [
                'hot_cue' => $cueData['hot_cue'],
                'type' => $cueData['type'] == 2 ? 'loop' : 'cue',
                'time' => $cueData['time'],
                'loop_time' => $cueData['type'] == 2 ? $cueData['loop_time'] : null,
                'comment' => ''
            ];
            
            $offset += 56;
        }
        
        return $cues;
    }
    
    private function parsePCO2Section($sectionData) {
        $cues = [];
        
        if (strlen($sectionData) < 20) {
            return $cues;
        }
        
        $header = unpack(
            'Nlen_header/' .
            'Nlen_tag/' .
            'Vtype/' .
            'vlencues',
            substr($sectionData, 4, 14)
        );
        
        $numCues = $header['lencues'] ?? 0;
        $offset = 20;
        
        for ($i = 0; $i < $numCues && $offset < strlen($sectionData); $i++) {
            if ($offset + 16 > strlen($sectionData)) break;
            
            $entryHeader = unpack(
                'Nlen_header/' .
                'Nlen_entry/' .
                'Vhot_cue',
                substr($sectionData, $offset + 4, 12)
            );
            
            $entryLen = $entryHeader['len_entry'] ?? 0;
            if ($entryLen == 0 || $offset + $entryLen > strlen($sectionData)) break;
            
            $cueData = unpack(
                'Ctype/' .
                'Cu1/' .
                'vu2/' .
                'Vtime/' .
                'Vloop_time/' .
                'Ccolor_id',
                substr($sectionData, $offset + 16, 14)
            );
            
            // Extract comment if present
            $comment = '';
            if ($offset + 40 < strlen($sectionData)) {
                $commentLen = unpack('V', substr($sectionData, $offset + 40, 4))[1];
                if ($commentLen > 0 && $offset + 44 + $commentLen <= strlen($sectionData)) {
                    $commentBytes = substr($sectionData, $offset + 44, $commentLen);
                    $comment = mb_convert_encoding($commentBytes, 'UTF-8', 'UTF-16BE');
                    // Remove trailing null
                    $comment = rtrim($comment, "\x00");
                }
            }
            
            $cues[] = [
                'hot_cue' => $entryHeader['hot_cue'],
                'type' => $cueData['type'] == 2 ? 'loop' : 'cue',
                'time' => $cueData['time'],
                'loop_time' => $cueData['type'] == 2 ? $cueData['loop_time'] : null,
                'color_id' => $cueData['color_id'] ?? 0,
                'comment' => $comment
            ];
            
            $offset += $entryLen;
        }
        
        return $cues;
    }

    public static function findAnlzFiles($pioneerPath) {
        $anlzFiles = [];
        
        $anlzDir = $pioneerPath . '/USBANLZ';
        if (!is_dir($anlzDir)) {
            return $anlzFiles;
        }

        $extensions = ['DAT', 'EXT', '2EX'];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($anlzDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtoupper(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($ext, $extensions) || 
                    (substr($file->getFilename(), -3) === '2EX')) {
                    $anlzFiles[] = $file->getPathname();
                }
            }
        }

        return $anlzFiles;
    }
}
