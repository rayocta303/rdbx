<?php

require_once __DIR__ . '/src/Utils/DatabaseRecovery.php';

use RekordboxReader\Utils\DatabaseRecovery;

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

$logger = new SimpleLogger();

// Test files
$corruptDb = __DIR__ . '/Rekordbox-USB-Corrupted/PIONEER/rekordbox/export.pdb';
$recoveredDb = __DIR__ . '/Rekordbox-USB-Corrupted/PIONEER/rekordbox/export_test_recovery.pdb';
$referenceDb = null; // NO REFERENCE DATABASE - this is the key test

echo "========================================\n";
echo "TESTING RECOVERY WITHOUT REFERENCE DB\n";
echo "========================================\n\n";

if (!file_exists($corruptDb)) {
    echo "ERROR: Corrupt database not found: $corruptDb\n";
    exit(1);
}

echo "Corrupt DB:    $corruptDb\n";
echo "Recovered DB:  $recoveredDb\n";
echo "Reference DB:  " . ($referenceDb ? $referenceDb : "NONE (testing without reference)") . "\n\n";

// Create recovery instance
$recovery = new DatabaseRecovery($corruptDb, $recoveredDb, $referenceDb, $logger);

echo "Starting full recovery...\n\n";

try {
    $result = $recovery->recoverAll();
    
    echo "\n========================================\n";
    echo "RECOVERY RESULT: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    echo "========================================\n\n";
    
    // Get recovery logs
    $log = $recovery->getRecoveryLog();
    echo "Recovery Log:\n";
    foreach ($log as $entry) {
        echo "  - $entry\n";
    }
    
    // Get stats
    $stats = $recovery->getStats();
    echo "\nRecovery Statistics:\n";
    foreach ($stats as $key => $value) {
        echo "  $key: $value\n";
    }
    
    // Check if recovered file exists
    if (file_exists($recoveredDb)) {
        $originalSize = filesize($corruptDb);
        $recoveredSize = filesize($recoveredDb);
        echo "\n";
        echo "Original file size:  " . number_format($originalSize) . " bytes\n";
        echo "Recovered file size: " . number_format($recoveredSize) . " bytes\n";
        
        if ($recoveredSize > 0) {
            echo "\n✓ Recovered database file created successfully!\n";
        } else {
            echo "\n✗ Recovered database file is empty!\n";
        }
    } else {
        echo "\n✗ Recovered database file was not created!\n";
    }
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "========================================\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
