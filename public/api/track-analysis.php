<?php
// Increase memory limit for parsing Rekordbox data
ini_set('memory_limit', '256M');

header('Content-Type: application/json');

require_once __DIR__ . '/../../src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

// Per-track file-based cache to avoid memory issues
class TrackDataCache {
    private static $cacheTTL = 3600; // 1 hour  
    private static $cacheDir = __DIR__ . '/../../output/cache/tracks';
    
    public static function getTrack($trackId) {
        // Create cache directory if not exists
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        $cacheFile = self::$cacheDir . "/track_{$trackId}.cache";
        
        // Check if cache file exists and is valid
        if (file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            if ($cacheAge < self::$cacheTTL) {
                // Cache hit - load from file
                $serialized = file_get_contents($cacheFile);
                $track = unserialize($serialized);
                if ($track !== false) {
                    return $track;
                }
            }
        }
        
        // Cache miss - need to parse from full dataset
        // Unfortunately we need RekordboxReader but cache result per-track
        $exportPath = __DIR__ . '/../../Rekordbox-USB';
        if (!is_dir($exportPath)) {
            return null;
        }
        
        try {
            $reader = new RekordboxReader($exportPath, __DIR__ . '/../../output', false);
            $data = $reader->run();
            
            // Cache ALL tracks while we have the data loaded (one-time cost)
            foreach ($data['tracks'] as $t) {
                $cacheFile = self::$cacheDir . "/track_{$t['id']}.cache";
                $cacheData = [
                    'id' => $t['id'],
                    'waveform' => $t['waveform'] ?? null,
                    'beat_grid' => $t['beat_grid'] ?? null,
                    'cue_points' => $t['cue_points'] ?? []
                ];
                file_put_contents($cacheFile, serialize($cacheData), LOCK_EX);
            }
            
            // Find requested track
            $track = null;
            foreach ($data['tracks'] as $t) {
                if ($t['id'] == $trackId) {
                    $track = $t;
                    break;
                }
            }
            
            if (!$track) {
                return null;
            }
            
            return [
                'id' => $track['id'],
                'waveform' => $track['waveform'] ?? null,
                'beat_grid' => $track['beat_grid'] ?? null,
                'cue_points' => $track['cue_points'] ?? []
            ];
        } catch (\Exception $e) {
            error_log("TrackDataCache error: " . $e->getMessage());
            return null;
        }
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
