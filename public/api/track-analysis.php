<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

// Simple in-memory cache using static variable
class TrackDataCache {
    private static $data = null;
    private static $cacheTime = null;
    private static $cacheTTL = 3600; // 1 hour
    
    public static function getData() {
        // Check if cache is valid
        if (self::$data !== null && (time() - self::$cacheTime) < self::$cacheTTL) {
            return self::$data;
        }
        
        // Load fresh data
        $exportPath = __DIR__ . '/../../Rekordbox-USB';
        if (!is_dir($exportPath)) {
            return null;
        }
        
        $reader = new RekordboxReader($exportPath, __DIR__ . '/../../output', false);
        self::$data = $reader->run();
        self::$cacheTime = time();
        
        return self::$data;
    }
    
    public static function getTrack($trackId) {
        $data = self::getData();
        if (!$data || !isset($data['tracks'])) {
            return null;
        }
        
        foreach ($data['tracks'] as $track) {
            if ($track['id'] == $trackId) {
                return $track;
            }
        }
        
        return null;
    }
}

$trackId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$trackId) {
    http_response_code(400);
    echo json_encode(['error' => 'Track ID required']);
    exit;
}

try {
    $track = TrackDataCache::getTrack($trackId);
    
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
