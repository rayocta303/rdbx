<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

$trackId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$trackId) {
    http_response_code(400);
    echo json_encode(['error' => 'Track ID required']);
    exit;
}

try {
    $exportPath = __DIR__ . '/../../Rekordbox-USB';
    
    if (!is_dir($exportPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Export directory not found']);
        exit;
    }
    
    $reader = new RekordboxReader($exportPath, __DIR__ . '/../../output', false);
    $data = $reader->run();
    
    $track = null;
    foreach ($data['tracks'] as $t) {
        if ($t['id'] == $trackId) {
            $track = $t;
            break;
        }
    }
    
    if (!$track) {
        http_response_code(404);
        echo json_encode(['error' => 'Track not found']);
        exit;
    }
    
    // Return only analysis data (waveform, beat_grid, cue_points)
    $response = [
        'id' => $track['id'],
        'waveform' => $track['waveform'] ?? null,
        'beat_grid' => $track['beat_grid'] ?? null,
        'cue_points' => $track['cue_points'] ?? []
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
