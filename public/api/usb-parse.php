<?php
require_once __DIR__ . '/../../src/RekordboxReader.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

use RekordboxReader\RekordboxReader;
use RekordboxReader\Utils\Logger;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    if (!isset($_FILES['export_pdb']) || $_FILES['export_pdb']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No export.pdb file uploaded');
    }

    $tmpFile = $_FILES['export_pdb']['tmp_name'];
    $tmpDir = sys_get_temp_dir() . '/usb_parse_' . uniqid();
    mkdir($tmpDir, 0777, true);
    
    $pdbPath = $tmpDir . '/export.pdb';
    move_uploaded_file($tmpFile, $pdbPath);
    
    $logger = new Logger($tmpDir . '/parse.log');
    
    $pdbParser = new \RekordboxReader\Parsers\PdbParser($pdbPath, $logger);
    $pdbData = $pdbParser->parse();
    
    $artistAlbumParser = new \RekordboxReader\Parsers\ArtistAlbumParser($pdbParser, $logger);
    $genreParser = new \RekordboxReader\Parsers\GenreParser($pdbParser, $logger);
    $keyParser = new \RekordboxReader\Parsers\KeyParser($pdbParser, $logger);
    
    $trackParser = new \RekordboxReader\Parsers\TrackParser($pdbParser, $logger);
    $trackParser->setArtistAlbumParser($artistAlbumParser);
    $trackParser->setGenreParser($genreParser);
    $trackParser->setKeyParser($keyParser);
    $tracks = $trackParser->parseTracks();
    
    // Parse ANLZ files to get waveform and beatgrid data
    $anlzParser = new \RekordboxReader\Parsers\AnlzParser('', $logger);
    $pioneerPath = dirname($pdbPath);
    
    // Enrich tracks with ANLZ data
    foreach ($tracks as &$track) {
        if (!empty($track['analyze_path'])) {
            // Build full ANLZ path
            $anlzPath = $pioneerPath . '/' . $track['analyze_path'];
            
            // Try .EXT file first (detailed waveform), then .DAT, then .2EX
            $anlzFiles = [
                str_replace('.DAT', '.EXT', $anlzPath),
                $anlzPath,
                str_replace('.DAT', '.2EX', $anlzPath)
            ];
            
            $anlzData = null;
            foreach ($anlzFiles as $anlzFile) {
                if (file_exists($anlzFile)) {
                    $anlzParser = new \RekordboxReader\Parsers\AnlzParser($anlzFile, $logger);
                    $parsed = $anlzParser->parse();
                    
                    // Merge data, preferring detailed waveform
                    if ($anlzData === null) {
                        $anlzData = $parsed;
                    } else {
                        // Merge waveform data (prefer detailed from .EXT)
                        if (!empty($parsed['waveform']['three_band_detail'])) {
                            $anlzData['waveform']['three_band_detail'] = $parsed['waveform']['three_band_detail'];
                        }
                        if (!empty($parsed['waveform']['three_band_preview'])) {
                            $anlzData['waveform']['three_band_preview'] = $parsed['waveform']['three_band_preview'];
                        }
                    }
                }
            }
            
            if ($anlzData) {
                $track['waveform'] = $anlzData['waveform'];
                $track['beat_grid'] = $anlzData['beat_grid'];
                $track['cue_points'] = $anlzData['cue_points'];
            }
        }
    }
    unset($track);
    
    $playlistParser = new \RekordboxReader\Parsers\PlaylistParser($pdbParser, $logger);
    $playlists = $playlistParser->parsePlaylists();
    
    $lightTracks = [];
    foreach ($tracks as $track) {
        // Prepare waveform data for frontend
        $waveformData = null;
        if (isset($track['waveform'])) {
            $waveformData = [
                'preview' => $track['waveform']['preview'] ?? null,
                'detail' => $track['waveform']['detail'] ?? null,
                'color' => $track['waveform']['color'] ?? null,
                'preview_data' => $track['waveform']['preview_data'] ?? null,
                'color_data' => $track['waveform']['color_data'] ?? null,
                'three_band_preview' => $track['waveform']['three_band_preview'] ?? null,
                'three_band_detail' => $track['waveform']['three_band_detail'] ?? null
            ];
        }
        
        $lightTracks[] = [
            'id' => $track['id'],
            'title' => $track['title'],
            'artist' => $track['artist'],
            'album' => $track['album'],
            'label' => $track['label'] ?? '',
            'key' => $track['key'],
            'key_id' => $track['key_id'] ?? 0,
            'genre' => $track['genre'],
            'bpm' => $track['bpm'],
            'duration' => $track['duration'],
            'year' => $track['year'] ?? 0,
            'rating' => $track['rating'] ?? 0,
            'file_path' => $track['file_path'],
            'analyze_path' => $track['analyze_path'] ?? '',
            'play_count' => $track['play_count'] ?? 0,
            'comment' => $track['comment'] ?? '',
            'cue_points' => $track['cue_points'] ?? [],
            'waveform' => $waveformData,
            'beat_grid' => $track['beat_grid'] ?? [],
            'sample_rate' => $track['sample_rate'] ?? 0,
            'bitrate' => $track['bitrate'] ?? 0,
            'color_id' => $track['color_id'] ?? 0,
            'artwork_id' => $track['artwork_id'] ?? 0,
            'artwork_path' => $track['artwork_path'] ?? ''
        ];
    }
    
    rmdirRecursive($tmpDir);
    
    // Prepare complete playlist data with hierarchy
    $playlistsData = [];
    foreach ($playlists as $playlist) {
        $playlistsData[] = [
            'id' => $playlist['id'],
            'name' => $playlist['name'],
            'parent_id' => $playlist['parent_id'] ?? 0,
            'sort_order' => $playlist['sort_order'] ?? 0,
            'is_folder' => $playlist['is_folder'] ?? false,
            'entries' => $playlist['entries'] ?? [],
            'track_count' => $playlist['track_count'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tracks' => $lightTracks,
        'playlists' => $playlistsData,
        'metadata' => [
            'total_tracks' => count($lightTracks),
            'total_playlists' => count($playlistsData),
            'parsed_at' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    
    if (isset($tmpDir) && is_dir($tmpDir)) {
        rmdirRecursive($tmpDir);
    }
}

function rmdirRecursive($dir) {
    if (!is_dir($dir)) return;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? rmdirRecursive($path) : unlink($path);
    }
    rmdir($dir);
}
