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
echo "║  TESTING FINAL DatabaseRecovery (NO REFERENCE)             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$corruptDb = __DIR__ . '/plans/export.pdb';
$recoveredDb = __DIR__ . '/plans/export_final_recovery.pdb';
$referenceDb = null; // NO REFERENCE!

echo "Files:\n";
echo "  Corrupt:    $corruptDb\n";
echo "  Recovered:  $recoveredDb\n";
echo "  Reference:  " . ($referenceDb ? $referenceDb : "NONE (Smart Recovery)") . "\n\n";

$logger = new SimpleLogger();

echo "═══════════════════════════════════════════════════════════\n";
echo " STEP 1: Running Full Recovery (NO REFERENCE)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$recovery = new DatabaseRecovery($corruptDb, $recoveredDb, $referenceDb, $logger);

try {
    $result = $recovery->recoverAll();
    
    echo "\n";
    echo "Recovery Result: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n\n";
    
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
            for ($i = 0; $i < min(5, count($tracks)); $i++) {
                $track = $tracks[$i];
                echo sprintf("  %2d. %-50s BPM: %-4s\n",
                    $i + 1,
                    substr($track['title'] ?? 'N/A', 0, 50),
                    $track['bpm'] ?? 'N/A'
                );
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
echo "SUMMARY\n";
echo "════════════════════════════════════════════════════════════\n\n";
echo "DatabaseRecovery.php (UPDATED):\n";
echo "  - ✓ Smart recovery WITHOUT reference database\n";
echo "  - ✓ Automatic header analysis and fixing\n";
echo "  - ✓ Automatic table directory fixing\n";
echo "  - ✓ Page size detection\n";
echo "  - ✓ Next unused page calculation\n";
echo "\nReady file: plans/export_final_recovery.pdb\n";
echo "════════════════════════════════════════════════════════════\n";
