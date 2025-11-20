<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

$exportPath = __DIR__ . '/Rekordbox-USB';
$reader = new RekordboxReader($exportPath, __DIR__ . '/output', true);
$data = $reader->run();

echo "\n=== TRACKS DEBUG ===\n\n";
foreach ($data['tracks'] as $idx => $track) {
    echo "Track " . ($idx + 1) . ":\n";
    echo "  ID: " . $track['id'] . "\n";
    echo "  Title: " . $track['title'] . "\n";
    echo "  Artist: " . $track['artist'] . "\n";
    echo "  Key: '" . $track['key'] . "'\n";
    echo "  Key ID: " . ($track['key_id'] ?? 'N/A') . "\n";
    echo "  BPM: " . $track['bpm'] . "\n";
    echo "  Genre: " . $track['genre'] . "\n";
    echo "  Analyze Path: " . ($track['analyze_path'] ?? '') . "\n";
    echo "\n";
}

echo "\n=== PLAYLISTS DEBUG ===\n\n";
foreach ($data['playlists'] as $idx => $playlist) {
    echo "Playlist " . ($idx + 1) . ":\n";
    echo "  ID: " . $playlist['id'] . "\n";
    echo "  Name: '" . $playlist['name'] . "'\n";
    echo "  Parent ID: " . $playlist['parent_id'] . "\n";
    echo "  Is Folder: " . ($playlist['is_folder'] ? 'Yes' : 'No') . "\n";
    echo "  Track Count: " . $playlist['track_count'] . "\n";
    echo "  Entries: " . implode(', ', $playlist['entries']) . "\n";
    echo "\n";
}
