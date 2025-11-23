<?php

require_once __DIR__ . '/src/Utils/DatabaseRecovery.php';
require_once __DIR__ . '/src/RekordboxReader.php';

use RekordboxReader\Utils\DatabaseRecovery;
use RekordboxReader\RekordboxReader;

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

echo "========================================\n";
echo "RANDOM CORRUPTION TEST\n";
echo "========================================\n\n";

$normalDb = __DIR__ . '/Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb';
$corruptedDb = __DIR__ . '/Rekordbox-USB-Corrupted/PIONEER/rekordbox/export_random_corrupt.pdb';
$recoveredDb = __DIR__ . '/Rekordbox-USB-Corrupted/PIONEER/rekordbox/export_random_recovered.pdb';

if (!file_exists($normalDb)) {
    echo "ERROR: Normal database not found: $normalDb\n";
    exit(1);
}

// Step 1: Create corrupted copy with RANDOM corruption
echo "Step 1: Creating corrupted database with random corruption...\n";
$data = file_get_contents($normalDb);
$originalSize = strlen($data);
echo "  Original size: " . number_format($originalSize) . " bytes\n";

// Determine data area (skip first 1024 bytes of header)
$headerSize = 1024;
$dataStart = $headerSize;
$dataEnd = $originalSize;
$dataSize = $dataEnd - $dataStart;

echo "  Header area: 0 - $headerSize\n";
echo "  Data area: $dataStart - $dataEnd (" . number_format($dataSize) . " bytes)\n\n";

// Apply random corruption to DATA AREA
$corruptionScenarios = [
    [
        'name' => 'Random byte flips',
        'count' => 50,
        'description' => 'Flip random bytes in data area'
    ],
    [
        'name' => 'Random byte sequences',
        'count' => 10,
        'description' => 'Replace random 16-byte sequences with zeros'
    ],
    [
        'name' => 'Random character corruption',
        'count' => 30,
        'description' => 'Replace random characters with invalid control chars'
    ]
];

$totalCorruptions = 0;
$corruptedPositions = [];

foreach ($corruptionScenarios as $scenario) {
    echo "Applying: {$scenario['name']} ({$scenario['description']})\n";
    
    for ($i = 0; $i < $scenario['count']; $i++) {
        // Random position in data area
        $pos = rand($dataStart, $dataEnd - 20);
        
        if ($scenario['name'] === 'Random byte flips') {
            // Flip single random byte
            $data[$pos] = chr(rand(0, 255));
            $corruptedPositions[] = $pos;
            $totalCorruptions++;
            
        } elseif ($scenario['name'] === 'Random byte sequences') {
            // Replace 16 bytes with zeros
            for ($j = 0; $j < 16; $j++) {
                $data[$pos + $j] = chr(0);
            }
            $corruptedPositions[] = "$pos-" . ($pos + 16);
            $totalCorruptions += 16;
            
        } elseif ($scenario['name'] === 'Random character corruption') {
            // Replace with control characters
            $data[$pos] = chr(rand(1, 31));
            $corruptedPositions[] = $pos;
            $totalCorruptions++;
        }
    }
}

echo "  Total corruptions applied: $totalCorruptions bytes\n";
echo "  Corruption percentage: " . round(($totalCorruptions / $dataSize) * 100, 2) . "%\n\n";

// Save corrupted database
file_put_contents($corruptedDb, $data);
echo "✓ Corrupted database saved to: $corruptedDb\n\n";

// Step 2: Run recovery WITHOUT reference database
echo "========================================\n";
echo "Step 2: Running recovery WITHOUT reference database...\n";
echo "========================================\n\n";

$logger = new SimpleLogger();
$recovery = new DatabaseRecovery($corruptedDb, $recoveredDb, null, $logger);

try {
    $result = $recovery->recoverAll();
    
    echo "\n========================================\n";
    echo "RECOVERY RESULT: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    echo "========================================\n\n";
    
    // Get recovery logs
    $log = $recovery->getRecoveryLog();
    echo "Recovery Log (last 10 entries):\n";
    $lastLogs = array_slice($log, -10);
    foreach ($lastLogs as $entry) {
        echo "  - $entry\n";
    }
    
    // Get stats
    $stats = $recovery->getStats();
    echo "\nRecovery Statistics:\n";
    foreach ($stats as $key => $value) {
        echo "  $key: $value\n";
    }
    
    // Check recovered file
    if (file_exists($recoveredDb)) {
        $originalSize = filesize($normalDb);
        $corruptedSize = filesize($corruptedDb);
        $recoveredSize = filesize($recoveredDb);
        
        echo "\n";
        echo "Original file size:   " . number_format($originalSize) . " bytes\n";
        echo "Corrupted file size:  " . number_format($corruptedSize) . " bytes\n";
        echo "Recovered file size:  " . number_format($recoveredSize) . " bytes\n";
        
        if ($recoveredSize > 0) {
            echo "\n✓ Recovered database file created successfully!\n";
            
            // Try to read recovered database
            echo "\n========================================\n";
            echo "Step 3: Testing if recovered database is readable...\n";
            echo "========================================\n\n";
            
            // Copy to proper location
            $recoveredPath = __DIR__ . '/Rekordbox-USB-Random-Recovered';
            @mkdir($recoveredPath . '/PIONEER/rekordbox', 0755, true);
            copy($recoveredDb, $recoveredPath . '/PIONEER/rekordbox/export.pdb');
            
            try {
                $reader = new RekordboxReader($recoveredPath);
                $result = $reader->run();
                
                echo "✓ Database is READABLE!\n\n";
                echo "Parse Results:\n";
                echo "  Tracks found: " . count($result['tracks'] ?? []) . "\n";
                echo "  Playlists found: " . count($result['playlists'] ?? []) . "\n";
                echo "  Artists found: " . count($result['artists'] ?? []) . "\n";
                echo "  Albums found: " . count($result['albums'] ?? []) . "\n";
                echo "  Genres found: " . count($result['genres'] ?? []) . "\n";
                
                echo "\n========================================\n";
                echo "FINAL RESULT: RECOVERY SUCCESSFUL!\n";
                echo "Database corrupted with $totalCorruptions random bytes\n";
                echo "Recovery completed WITHOUT reference database\n";
                echo "Recovered database is readable and contains data\n";
                echo "========================================\n";
                
            } catch (Exception $e) {
                echo "✗ Database is NOT readable: " . $e->getMessage() . "\n";
                echo "\n========================================\n";
                echo "FINAL RESULT: RECOVERY PARTIALLY SUCCESSFUL\n";
                echo "File was recovered but contains errors\n";
                echo "========================================\n";
            }
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
}
