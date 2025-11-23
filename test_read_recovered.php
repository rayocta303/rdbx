<?php

require_once __DIR__ . '/src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

echo "========================================\n";
echo "TESTING READING RECOVERED DATABASE\n";
echo "========================================\n\n";

$recoveredPath = __DIR__ . '/Rekordbox-USB-Recovered';

if (!file_exists($recoveredPath . '/PIONEER/rekordbox/export.pdb')) {
    echo "ERROR: Recovered database not found\n";
    exit(1);
}

echo "Reading recovered database from: $recoveredPath\n\n";

try {
    $reader = new RekordboxReader($recoveredPath);
    $result = $reader->run();
    
    echo "Parse Results:\n";
    echo "  Tracks found: " . count($result['tracks'] ?? []) . "\n";
    echo "  Playlists found: " . count($result['playlists'] ?? []) . "\n";
    echo "  Artists found: " . count($result['artists'] ?? []) . "\n";
    echo "  Albums found: " . count($result['albums'] ?? []) . "\n";
    echo "  Genres found: " . count($result['genres'] ?? []) . "\n";
    echo "  Keys found: " . count($result['keys'] ?? []) . "\n";
    echo "  Colors found: " . count($result['colors'] ?? []) . "\n";
    
    $stats = $reader->getStats();
    echo "\nProcessing Statistics:\n";
    foreach ($stats as $key => $value) {
        echo "  $key: $value\n";
    }
    
    // Show sample tracks if any
    if (!empty($result['tracks'])) {
        echo "\nSample tracks:\n";
        $sampleTracks = array_slice($result['tracks'], 0, 3);
        foreach ($sampleTracks as $idx => $track) {
            echo "  Track " . ($idx + 1) . ":\n";
            echo "    ID: " . ($track['id'] ?? 'N/A') . "\n";
            echo "    Title: " . ($track['title'] ?? 'N/A') . "\n";
            echo "    Artist: " . ($track['artist'] ?? 'N/A') . "\n";
            echo "    Path: " . ($track['file_path'] ?? 'N/A') . "\n";
        }
    }
    
    echo "\n========================================\n";
    if (!empty($result['tracks'])) {
        echo "RESULT: ✓ Database is readable and contains data!\n";
    } else {
        echo "RESULT: ⚠ Database is readable but contains no tracks\n";
    }
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "========================================\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
