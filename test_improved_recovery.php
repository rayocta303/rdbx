<?php

require_once __DIR__ . '/src/Utils/DatabaseRecovery.php';
require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Parsers/TrackParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Utils\DatabaseRecovery;
use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Utils\Logger;

class SimpleLogger {
    public function debug($message) {
        echo "[DEBUG] $message\n";
    }
    
    public function info($message) {
        echo "[INFO] $message\n";
    }
    
    public function error($message) {
        echo "[ERROR] $message\n";
    }
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  TESTING IMPROVED RECOVERY FUNCTION                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$corruptDb = __DIR__ . '/plans/export.pdb';
$recoveredDb = __DIR__ . '/plans/export_improved_recovery.pdb';
$referenceDb = __DIR__ . '/Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb';

echo "Files:\n";
echo "  Corrupt:    $corruptDb\n";
echo "  Recovered:  $recoveredDb\n";
echo "  Reference:  $referenceDb\n\n";

$logger = new SimpleLogger();

echo "═══════════════════════════════════════════════════════════\n";
echo " STEP 1: Running Improved Recovery\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$recovery = new DatabaseRecovery($corruptDb, $recoveredDb, $referenceDb, $logger);

try {
    $result = $recovery->recoverAll();
    
    echo "\n";
    echo "Recovery Result: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n\n";
    
    // Get recovery log
    $log = $recovery->getRecoveryLog();
    echo "Recovery Log:\n";
    foreach ($log as $entry) {
        if (strpos($entry, '===') !== false) {
            echo "\n$entry\n";
        } else {
            echo "  • $entry\n";
        }
    }
    
    // Get stats
    $stats = $recovery->getStats();
    echo "\nStats:\n";
    foreach ($stats as $key => $value) {
        echo "  $key: $value\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo " STEP 2: Verifying Recovered File\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if (file_exists($recoveredDb)) {
    try {
        $pdbLogger = new Logger('output', false);
        $parser = new PdbParser($recoveredDb, $pdbLogger);
        $data = $parser->parse();
        
        echo "✓ Database parsed successfully\n";
        echo "  - Tables: " . count($data['tables']) . "\n\n";
        
        $trackParser = new TrackParser($parser, $pdbLogger);
        $tracks = $trackParser->parseTracks();
        
        echo "✓ Tracks parsed: " . count($tracks) . " tracks\n\n";
        
        if (count($tracks) > 0) {
            echo "Sample tracks:\n";
            for ($i = 0; $i < min(3, count($tracks)); $i++) {
                $track = $tracks[$i];
                echo "  " . ($i + 1) . ". " . ($track['title'] ?? 'N/A') . " - BPM: " . ($track['bpm'] ?? 'N/A') . "\n";
            }
        }
        
        echo "\n";
        echo "✓✓✓ RECOVERY SUCCESSFUL ✓✓✓\n";
        echo "\nRecovered file: $recoveredDb\n";
        echo "Total tracks: " . count($tracks) . "\n";
        
    } catch (Exception $e) {
        echo "✗ Failed to parse recovered file\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Recovered file not created\n";
}

echo "\n════════════════════════════════════════════════════════════\n";
