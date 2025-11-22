<?php

spl_autoload_register(function ($class) {
    $prefix = 'RekordboxReader\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use RekordboxReader\RekordboxReader;
use RekordboxReader\Utils\Logger;

$exportPath = './Rekordbox-USB';
$outputPath = './output';
$logger = new Logger('DEBUG');

try {
    $reader = new RekordboxReader($exportPath, $outputPath, false);
    $data = $reader->run();
    
    echo "\n=== PLAYLIST DATA ===\n\n";
    
    if (isset($data['playlists'])) {
        foreach ($data['playlists'] as $playlist) {
            echo "Playlist ID: {$playlist['id']}\n";
            echo "Playlist Name: {$playlist['name']}\n";
            echo "Parent ID: {$playlist['parent_id']}\n";
            echo "Is Folder: " . ($playlist['is_folder'] ? 'Yes' : 'No') . "\n";
            echo "Track Count: {$playlist['track_count']}\n";
            
            if (!empty($playlist['entries'])) {
                echo "Entries (Track IDs): " . implode(', ', array_slice($playlist['entries'], 0, 15)) . "\n";
                if (count($playlist['entries']) > 15) {
                    echo "... and " . (count($playlist['entries']) - 15) . " more\n";
                }
                
                // Show first few tracks detail
                echo "\nFirst 5 Tracks in Playlist:\n";
                $trackCount = 0;
                foreach ($playlist['entries'] as $trackId) {
                    if ($trackCount >= 5) break;
                    
                    // Find track
                    $track = null;
                    if (isset($data['tracks'])) {
                        foreach ($data['tracks'] as $t) {
                            if ($t['id'] == $trackId) {
                                $track = $t;
                                break;
                            }
                        }
                    }
                    
                    if ($track) {
                        $trackCount++;
                        echo "  $trackCount. [{$track['id']}] {$track['title']} - {$track['artist']} (BPM: {$track['bpm']})\n";
                    }
                }
            }
            
            echo "\n" . str_repeat('-', 80) . "\n\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total Playlists: " . count($data['playlists']) . "\n";
    echo "Total Tracks: " . count($data['tracks']) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
