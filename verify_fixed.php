<?php

require_once __DIR__ . '/src/RekordboxReader.php';
require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Parsers/TrackParser.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Utils\Logger;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFYING FIXED DATABASE                                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$files = [
    'Normal (Reference)' => 'Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb',
    'Corrupt (Original)'  => 'plans/export.pdb',
    'Fixed (Recovered)'   => 'plans/export_fixed.pdb'
];

foreach ($files as $label => $file) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo " $label\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "File: $file\n\n";
    
    if (!file_exists($file)) {
        echo "✗ File not found!\n";
        continue;
    }
    
    try {
        $logger = new Logger('output', false);
        $parser = new PdbParser($file, $logger);
        
        echo "[1] Parsing database...\n";
        $data = $parser->parse();
        echo "    ✓ Database parsed successfully\n";
        echo "    - Tables: " . count($data['tables']) . "\n\n";
        
        echo "[2] Reading tracks...\n";
        $trackParser = new TrackParser($parser, $logger);
        $tracks = $trackParser->parseTracks();
        echo "    ✓ Tracks parsed: " . count($tracks) . " tracks\n\n";
        
        if (count($tracks) > 0) {
            echo "[3] Sample tracks:\n";
            for ($i = 0; $i < min(3, count($tracks)); $i++) {
                $track = $tracks[$i];
                echo "\n    Track " . ($i + 1) . ":\n";
                echo "    - ID:     " . ($track['id'] ?? 'N/A') . "\n";
                echo "    - Title:  " . ($track['title'] ?? 'N/A') . "\n";
                echo "    - Artist: " . ($track['artist'] ?? 'N/A') . "\n";
                echo "    - BPM:    " . ($track['bpm'] ?? 'N/A') . "\n";
                echo "    - Key:    " . ($track['key'] ?? 'N/A') . "\n";
            }
        }
        
        echo "\n\n✓✓✓ FILE IS VALID AND READABLE ✓✓✓\n";
        
    } catch (Exception $e) {
        echo "\n✗✗✗ PARSE FAILED ✗✗✗\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
}

echo "\n\n";
echo "════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "════════════════════════════════════════════════════════════\n";
echo "\nJika 'Fixed (Recovered)' file berhasil dibaca dengan data\n";
echo "yang sama seperti 'Normal (Reference)', maka recovery SUKSES!\n";
echo "\nFile yang siap digunakan:\n";
echo "  → plans/export_fixed.pdb\n";
echo "\nCopy file ini ke USB drive di:\n";
echo "  → /PIONEER/rekordbox/export.pdb\n";
echo "════════════════════════════════════════════════════════════\n";
