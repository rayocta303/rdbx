<?php

namespace RekordboxReader\Parsers;

class PdbParser {
    const TABLE_TRACKS = 0;
    const TABLE_GENRES = 1;
    const TABLE_ARTISTS = 2;
    const TABLE_ALBUMS = 3;
    const TABLE_LABELS = 4;
    const TABLE_KEYS = 5;
    const TABLE_COLORS = 6;
    const TABLE_PLAYLIST_TREE = 7;
    const TABLE_PLAYLIST_ENTRIES = 8;
    const TABLE_HISTORY_PLAYLISTS = 11;
    const TABLE_HISTORY_ENTRIES = 12;
    const TABLE_ARTWORK = 13;
    const TABLE_COLUMNS = 16;
    const TABLE_HISTORY = 19;

    private $pdbPath;
    private $logger;
    private $data;
    private $header;
    private $tables;

    public function __construct($pdbPath, $logger = null) {
        $this->pdbPath = $pdbPath;
        $this->logger = $logger;
        $this->data = '';
        $this->header = [];
        $this->tables = [];
    }

    public function parse() {
        if (!file_exists($this->pdbPath)) {
            throw new \Exception("PDB file not found: " . $this->pdbPath);
        }

        $this->data = file_get_contents($this->pdbPath);

        if ($this->logger) {
            $this->logger->info("Parsing PDB file: " . $this->pdbPath);
        }

        $this->header = $this->parseHeader();
        $this->parseTables();

        return [
            'header' => $this->header,
            'tables' => $this->tables
        ];
    }

    private function parseHeader() {
        if (strlen($this->data) < 32) {
            throw new \Exception("PDB file too small to contain valid header");
        }

        $header = unpack(
            'Vsignature/' .
            'Vpage_size/' .
            'Vnum_tables/' .
            'Vnext_unused_page/' .
            'Vunknown/' .
            'Vsequence',
            substr($this->data, 0, 24)
        );

        if ($this->logger) {
            $this->logger->debug("PDB Header: {$header['num_tables']} tables, page size: {$header['page_size']} bytes");
        }

        return $header;
    }

    private function parseTables() {
        $pageSize = $this->header['page_size'];
        $numTables = $this->header['num_tables'];
        
        $offset = 28;

        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($this->data)) {
                break;
            }

            $tableData = unpack(
                'Vtype/' .
                'Vempty_candidate/' .
                'Vfirst_page/' .
                'Vlast_page',
                substr($this->data, $offset, 16)
            );

            $tableType = $tableData['type'];

            $this->tables[$tableType] = [
                'type' => $tableType,
                'type_name' => $this->getTableName($tableType),
                'empty_candidate' => $tableData['empty_candidate'],
                'first_page' => $tableData['first_page'],
                'last_page' => $tableData['last_page'],
                'rows' => []
            ];

            $offset += 16;
        }
    }

    private function getTableName($tableType) {
        $tableNames = [
            0 => 'tracks',
            1 => 'genres',
            2 => 'artists',
            3 => 'albums',
            4 => 'labels',
            5 => 'keys',
            6 => 'colors',
            7 => 'playlist_tree',
            8 => 'playlist_entries',
            11 => 'history_playlists',
            12 => 'history_entries',
            13 => 'artwork',
            16 => 'columns',
            19 => 'history'
        ];
        return $tableNames[$tableType] ?? "unknown_{$tableType}";
    }

    public function getTable($tableType) {
        return $this->tables[$tableType] ?? null;
    }

    public function readPage($pageIndex) {
        $pageSize = $this->header['page_size'];
        $offset = $pageIndex * $pageSize;

        if ($offset + $pageSize > strlen($this->data)) {
            return null;
        }

        return substr($this->data, $offset, $pageSize);
    }

    public function extractString($data, $offset) {
        if ($offset >= strlen($data)) {
            return ['', $offset];
        }

        $flags = ord($data[$offset]);
        $offset += 1;

        if (($flags & 0x01) == 1) {
            $totalLength = $flags >> 1;
            $dataLength = $totalLength - 1;
            
            if ($dataLength <= 0 || $offset + $dataLength > strlen($data)) {
                return ['', $offset];
            }
            
            $text = substr($data, $offset, $dataLength);
            return [$text, $offset + $dataLength];
        }

        elseif ($flags == 0x40) {
            if ($offset + 1 >= strlen($data)) {
                return ['', $offset];
            }
            $lengthData = unpack('v', substr($data, $offset, 2));
            $length = $lengthData[1];
            $offset += 2;
            if ($offset + $length > strlen($data)) {
                return ['', $offset];
            }
            $text = substr($data, $offset, $length);
            return [$text, $offset + $length];
        }

        elseif ($flags == 0x90) {
            if ($offset + 1 >= strlen($data)) {
                return ['', $offset];
            }
            $lengthData = unpack('v', substr($data, $offset, 2));
            $length = $lengthData[1];
            $offset += 2;
            $byteLength = $length * 2;
            if ($offset + $byteLength > strlen($data)) {
                return ['', $offset];
            }
            $rawText = substr($data, $offset, $byteLength);
            $text = mb_convert_encoding($rawText, 'UTF-8', 'UTF-16LE');
            return [$text, $offset + $byteLength];
        }

        return ['', $offset];
    }

    public function getHeader() {
        return $this->header;
    }

    public function getTables() {
        return $this->tables;
    }
}
