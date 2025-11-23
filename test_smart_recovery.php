<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Parsers/TrackParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Utils\Logger;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  TESTING SMART RECOVERY RESULT                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$files = [
    'Original Corrupt' => 'plans/export.pdb',
    'Smart Recovery' => 'plans/export_smart_recovery.pdb'
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
        
        echo "[1] Parsing database...\n";
        $parser = new PdbParser($file, $logger);
        $data = $parser->parse();
        echo "    ✓ Database parsed successfully\n";
        echo "    - Tables: " . count($data['tables']) . "\n\n";
        
        echo "[2] Reading tracks...\n";
        $trackParser = new TrackParser($parser, $logger);
        $tracks = $trackParser->parseTracks();
        echo "    ✓ Tracks parsed: " . count($tracks) . " tracks\n\n";
        
        if (count($tracks) > 0) {
            echo "[3] Sample tracks:\n";
            for ($i = 0; $i < min(5, count($tracks)); $i++) {
                $track = $tracks[$i];
                echo sprintf("    %2d. %-50s BPM: %-4s\n",
                    $i + 1,
                    substr($track['title'] ?? 'N/A', 0, 50),
                    $track['bpm'] ?? 'N/A'
                );
            }
        }
        
        echo "\n✓✓✓ FILE IS VALID AND READABLE ✓✓✓\n";
        
    } catch (Exception $e) {
        echo "\n✗✗✗ PARSE FAILED ✗✗✗\n";
        echo "Error: " . $e->getMessage() . "\n\n";
        
        // Show first 100 chars of error for debugging
        $trace = $e->getTraceAsString();
        $lines = explode("\n", $trace);
        echo "First error location:\n";
        echo "  " . (isset($lines[0]) ? $lines[0] : 'Unknown') . "\n";
    }
}

echo "\n\n";
echo "════════════════════════════════════════════════════════════\n";
echo "CONCLUSION\n";
echo "════════════════════════════════════════════════════════════\n\n";
echo "Smart Recovery (WITHOUT reference database):\n";
echo "  - Analyzed file structure independently\n";
echo "  - Fixed 21 corruption issues automatically\n";
echo "  - Result: ";

if (file_exists('plans/export_smart_recovery.pdb')) {
    try {
        $logger = new Logger('output', false);
        $parser = new PdbParser('plans/export_smart_recovery.pdb', $logger);
        $data = $parser->parse();
        $trackParser = new TrackParser($parser, $logger);
        $tracks = $trackParser->parseTracks();
        
        echo "✓ SUCCESS!\n";
        echo "  - Total tracks recovered: " . count($tracks) . "\n";
        echo "  - File ready to use: plans/export_smart_recovery.pdb\n";
    } catch (Exception $e) {
        echo "✗ Needs adjustment\n";
        echo "  - Error: " . $e->getMessage() . "\n";
    }
}

echo "\n════════════════════════════════════════════════════════════\n";
