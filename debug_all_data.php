<?php

require_once __DIR__ . '/src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

$exportPath = __DIR__ . '/Rekordbox-USB';

echo "=== COMPLETE DATA DEBUG ===\n\n";

$reader = new RekordboxReader($exportPath, __DIR__ . '/output', false);
$data = $reader->run();

echo "Total Tracks: " . count($data['tracks']) . "\n\n";

foreach ($data['tracks'] as $idx => $track) {
    echo "=== TRACK " . ($idx + 1) . " ===\n";
    echo "ID: {$track['id']}\n";
    echo "Title: {$track['title']}\n";
    echo "Artist: {$track['artist']}\n";
    echo "Genre: {$track['genre']}\n";
    echo "BPM: {$track['bpm']}\n";
    echo "Key: {$track['key']}\n";
    echo "Artist ID: {$track['artist_id']}\n";
    echo "Genre ID: {$track['genre_id']}\n";
    echo "Key ID: {$track['key_id']}\n";
    echo "File Path: {$track['file_path']}\n";
    echo "Analyze Path: {$track['analyze_path']}\n";
    echo "\n";
}

echo "\n=== PLAYLISTS ===\n";
foreach ($data['playlists'] as $playlist) {
    if (!$playlist['is_folder']) {
        echo "Playlist: {$playlist['name']} ({$playlist['track_count']} tracks)\n";
        echo "Track IDs: " . implode(', ', $playlist['entries']) . "\n\n";
    }
}
