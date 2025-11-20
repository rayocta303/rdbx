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
            'color' => null
        ];

        if (isset($this->sections['PWAV'])) {
            $waveform['preview'] = [
                'type' => 'monochrome',
                'data_length' => strlen($this->sections['PWAV'][0])
            ];
        }

        if (isset($this->sections['PWV3'])) {
            $waveform['detail'] = [
                'type' => 'monochrome',
                'data_length' => strlen($this->sections['PWV3'][0])
            ];
        }

        if (isset($this->sections['PWV5'])) {
            $waveform['color'] = [
                'type' => 'color',
                'data_length' => strlen($this->sections['PWV5'][0])
            ];
        }

        return $waveform;
    }

    private function extractCuePoints() {
        $cuePoints = [];

        if (isset($this->sections['PCOB'])) {
            if ($this->logger) {
                $this->logger->debug("Cue points section found");
            }
        }

        return $cuePoints;
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
