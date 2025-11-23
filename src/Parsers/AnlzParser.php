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

        // PQTZ section contains beat grid data
        if (!isset($this->sections['PQTZ']) || empty($this->sections['PQTZ'])) {
            if ($this->logger) {
                $this->logger->info("No PQTZ section found - beatgrid not available");
            }
            return $beatgrid;
        }

        $sectionData = $this->sections['PQTZ'][0];
        
        if ($this->logger) {
            $this->logger->info("PQTZ section found, size: " . strlen($sectionData) . " bytes");
        }
        
        // PQTZ section structure (all big-endian):
        // 0-3: fourcc 'PQTZ'
        // 4-7: len_header (u4)
        // 8-11: len_tag (u4)
        // 12-15: type (u4)
        // 16-19: unknown (u4, always 0x80000)
        // 20-23: num_beats (u4)
        // 24+: beat entries (8 bytes each)
        
        if (strlen($sectionData) < 24) {
            if ($this->logger) {
                $this->logger->warning("PQTZ section too short: " . strlen($sectionData) . " bytes");
            }
            return $beatgrid;
        }

        // Parse header (skip fourcc, len_header, len_tag, type, unknown)
        $header = unpack('Nnum_beats', substr($sectionData, 20, 4));
        $numBeats = $header['num_beats'];

        if ($this->logger) {
            $this->logger->info("PQTZ num_beats: {$numBeats}");
        }

        // Parse beat entries
        $offset = 24; // Start of beat data
        for ($i = 0; $i < $numBeats && $offset + 8 <= strlen($sectionData); $i++) {
            // Each beat entry is 8 bytes:
            // 0-1: beat_number (u2) - position within bar (1 = downbeat)
            // 2-3: tempo (u2) - BPM * 100
            // 4-7: time (u4) - time in milliseconds
            $beatData = unpack(
                'nbeat_number/' .
                'ntempo/' .
                'Ntime',
                substr($sectionData, $offset, 8)
            );

            $beatgrid[] = [
                'beat' => $beatData['beat_number'],
                'bpm' => $beatData['tempo'] / 100.0,
                'time' => $beatData['time'] / 1000.0  // Convert to seconds
            ];

            $offset += 8;
        }

        if ($this->logger) {
            $this->logger->info("Beatgrid extracted: " . count($beatgrid) . " beats, first beat: " . 
                (count($beatgrid) > 0 ? json_encode($beatgrid[0]) : 'none'));
        }

        return $beatgrid;
    }

    private function extractWaveform() {
        $waveform = [
            'preview' => null,
            'detail' => null,
            'color' => null,
            'preview_data' => null,
            'color_data' => null,
            'three_band_preview' => null,
            'three_band_detail' => null
        ];

        // Parse preview waveform (PWAV)
        if (isset($this->sections['PWAV'])) {
            $waveData = $this->parseWaveformData($this->sections['PWAV'][0], 'mono');
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

        // Parse color waveform (PWV5 - 3-band preview)
        if (isset($this->sections['PWV5'])) {
            $waveData = $this->parseWaveformData($this->sections['PWV5'][0], '3band');
            $waveform['color'] = [
                'type' => '3band',
                'data_length' => strlen($this->sections['PWV5'][0]),
                'samples' => count($waveData)
            ];
            $waveform['color_data'] = $waveData;
            $waveform['three_band_preview'] = $waveData;
        }

        // Parse 3-band detail waveform (PWV6 or PWV4)
        $detailSections = ['PWV6', 'PWV4'];
        foreach ($detailSections as $section) {
            if (isset($this->sections[$section])) {
                $waveData = $this->parseWaveformData($this->sections[$section][0], '3band');
                if (!empty($waveData)) {
                    $waveform['three_band_detail'] = $waveData;
                    break;
                }
            }
        }

        return $waveform;
    }
    
    private function parseWaveformData($sectionData, $type) {
        $waveData = [];
        
        if (strlen($sectionData) < 12) {
            return $waveData;
        }
        
        // Read len_header and len_tag from section (big-endian)
        $headerInfo = unpack('Nlen_header/Nlen_tag', substr($sectionData, 4, 8));
        $offset = $headerInfo['len_header'];
        $tagEnd = $headerInfo['len_tag'];
        
        // Each waveform sample size depends on type
        $sampleSize = 1;
        if ($type === '3band') {
            $sampleSize = 3; // 3 bytes: mid, high, low frequencies
        }
        
        // Read only up to len_tag to avoid padding/footer
        while ($offset + $sampleSize <= $tagEnd && $offset + $sampleSize <= strlen($sectionData)) {
            if ($type === '3band') {
                // 3-band waveform: mid, high, low frequency values
                // Each is 1 byte representing amplitude
                $sample = unpack('Cmid/Chigh/Clow', substr($sectionData, $offset, 3));
                $waveData[] = [
                    'mid' => $sample['mid'],
                    'high' => $sample['high'],
                    'low' => $sample['low']
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
        
        // PCOB section header
        $header = unpack(
            'Nlen_header/' .
            'Nlen_tag/' .
            'Ntype/' .
            'nlencues',
            substr($sectionData, 4, 14)
        );
        
        $numCues = $header['lencues'] ?? 0;
        $offset = $header['len_header'] ?? 24;
        
        for ($i = 0; $i < $numCues && $offset + 38 <= strlen($sectionData); $i++) {
            // PCOB cue entry structure:
            // Doc: https://djl-analysis.deepsymmetry.org/rekordbox-export-analysis/anlz.html#cue-list
            // 0-3: magic (always 0x00000000)
            // 4-7: len_header (u4 big-endian)
            // 8-11: len_entry (u4 big-endian)
            // 12-15: hot_cue (u4 big-endian) - 0=memory, 1=A, 2=B, 3=C, etc.
            // 16-19: status (u4 big-endian)
            // 20-27: unknown
            // 28: type (u1) - 1=cue, 2=loop
            // 29-31: padding
            // 32-35: time (u4 big-endian) - milliseconds
            // 36-39: loop_time (u4 big-endian)
            $cueData = unpack(
                'Nmagic/' .        // 0-3
                'Nlen_header/' .   // 4-7
                'Nlen_entry/' .    // 8-11
                'Nhot_cue/' .      // 12-15 *** CORRECT OFFSET ***
                'Nstatus/' .       // 16-19
                'Nunknown1/' .     // 20-23
                'Nunknown2/' .     // 24-27
                'Ctype/' .         // 28
                'x3/' .            // 29-31 skip padding
                'Ntime/' .         // 32-35 *** TIME IN MILLISECONDS ***
                'Nloop_time',      // 36-39
                substr($sectionData, $offset, 40)
            );
            
            $rawHotCue = $cueData['hot_cue'];
            
            // hot_cue: 0=memory, 1=A, 2=B, 3=C, 4=D, 5=E, 6=F, 7=G, 8=H
            if ($rawHotCue >= 1 && $rawHotCue <= 8) {
                $hotCueIndex = $rawHotCue - 1; // Convert to 0-based (0=A, 1=B, etc.)
                $hotCueLabel = chr(ord('A') + $hotCueIndex);
                
                $cues[] = [
                    'hot_cue' => $hotCueIndex,
                    'hot_cue_label' => $hotCueLabel,
                    'type' => $cueData['type'] == 2 ? 'loop' : 'cue',
                    'time' => $cueData['time'],
                    'loop_time' => $cueData['type'] == 2 ? $cueData['loop_time'] : null,
                    'comment' => ''
                ];
            }
            
            $offset += $cueData['len_entry'] ?? 56;
        }
        
        return $cues;
    }
    
    private function parsePCO2Section($sectionData) {
        $cues = [];
        
        if (strlen($sectionData) < 20) {
            return $cues;
        }
        
        // PCO2 section header
        $header = unpack(
            'Nlen_header/' .
            'Nlen_tag/' .
            'Ntype/' .
            'nlencues',
            substr($sectionData, 4, 14)
        );
        
        $numCues = $header['lencues'] ?? 0;
        $offset = $header['len_header'] ?? 20;
        
        for ($i = 0; $i < $numCues && $offset + 16 <= strlen($sectionData); $i++) {
            // PCO2 entry header (16 bytes):
            // Doc: https://djl-analysis.deepsymmetry.org/rekordbox-export-analysis/anlz.html#extended-cue-list
            // 0-3: fourcc 'PCP2'
            // 4-7: len_header (u4 big-endian)
            // 8-11: len_entry (u4 big-endian)
            // 12-15: hot_cue (u4 big-endian) - 0=memory, 1=A, 2=B, etc.
            $entryHeader = unpack(
                'a4fourcc/' .      // 0-3
                'Nlen_header/' .   // 4-7
                'Nlen_entry/' .    // 8-11
                'Nhot_cue',        // 12-15 *** CORRECT OFFSET ***
                substr($sectionData, $offset, 16)
            );
            
            $entryLen = $entryHeader['len_entry'] ?? 0;
            if ($entryLen == 0 || $offset + $entryLen > strlen($sectionData)) break;
            
            $entryHeaderLen = $entryHeader['len_header'] ?? 16;
            
            // PCO2 cue data (starts after entry header):
            // 0: type (u1) - 1=cue, 2=loop
            // 1-3: padding
            // 4-7: time (u4 big-endian) - milliseconds
            // 8-11: loop_time (u4 big-endian)
            // 12: color_id (u1)
            // 13-15: padding
            $cueData = unpack(
                'Ctype/' .         // offset entryHeaderLen + 0
                'x3/' .            // skip 3 bytes padding
                'Ntime/' .         // offset entryHeaderLen + 4 *** TIME IN MILLISECONDS ***
                'Nloop_time/' .    // offset entryHeaderLen + 8
                'Ccolor_id',       // offset entryHeaderLen + 12
                substr($sectionData, $offset + $entryHeaderLen, 13)
            );
            
            // Extract comment if present (starts at offset entryHeaderLen + 24)
            $comment = '';
            $commentOffset = $offset + $entryHeaderLen + 24;
            if ($commentOffset + 4 <= strlen($sectionData)) {
                $commentLenData = unpack('N', substr($sectionData, $commentOffset, 4));
                $commentLen = $commentLenData[1] ?? 0;
                if ($commentLen > 0 && $commentOffset + 4 + $commentLen <= strlen($sectionData)) {
                    $commentBytes = substr($sectionData, $commentOffset + 4, $commentLen);
                    $comment = mb_convert_encoding($commentBytes, 'UTF-8', 'UTF-16BE');
                    $comment = rtrim($comment, "\x00");
                }
            }
            
            $rawHotCue = $entryHeader['hot_cue'];
            
            // hot_cue: 0=memory, 1=A, 2=B, 3=C, 4=D, 5=E, 6=F, 7=G, 8=H
            if ($rawHotCue >= 1 && $rawHotCue <= 8) {
                $hotCueIndex = $rawHotCue - 1; // Convert to 0-based (0=A, 1=B, etc.)
                $hotCueLabel = chr(ord('A') + $hotCueIndex);
                
                $cues[] = [
                    'hot_cue' => $hotCueIndex,
                    'hot_cue_label' => $hotCueLabel,
                    'type' => $cueData['type'] == 2 ? 'loop' : 'cue',
                    'time' => $cueData['time'],
                    'loop_time' => $cueData['type'] == 2 ? $cueData['loop_time'] : null,
                    'color_id' => $cueData['color_id'] ?? 0,
                    'comment' => $comment
                ];
            }
            
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
