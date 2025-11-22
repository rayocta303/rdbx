<?php
require_once __DIR__ . '/../src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

// Load Rekordbox data directly
$data = null;
$stats = null;
$error = null;

try {
    $exportPath = __DIR__ . '/../Rekordbox-USB';
    
    if (is_dir($exportPath)) {
        $reader = new RekordboxReader($exportPath, __DIR__ . '/../output', false);
        $fullData = $reader->run();
        $stats = $reader->getStats();
        
        // Filter tracks to lightweight metadata only (no waveform/beat_grid)
        $lightTracks = [];
        foreach ($fullData['tracks'] as $track) {
            $lightTracks[] = [
                'id' => $track['id'],
                'title' => $track['title'],
                'artist' => $track['artist'],
                'album' => $track['album'],
                'label' => $track['label'] ?? '',
                'key' => $track['key'],
                'genre' => $track['genre'],
                'bpm' => $track['bpm'],
                'duration' => $track['duration'],
                'year' => $track['year'] ?? 0,
                'rating' => $track['rating'] ?? 0,
                'file_path' => $track['file_path'],
                'analyze_path' => $track['analyze_path'] ?? '',
                'play_count' => $track['play_count'] ?? 0,
                'comment' => $track['comment'] ?? ''
            ];
        }
        
        $data = [
            'tracks' => $lightTracks,
            'playlists' => $fullData['playlists']
        ];
    } else {
        $error = "Rekordbox-USB directory not found!";
        $data = ['tracks' => [], 'playlists' => []];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $data = ['tracks' => [], 'playlists' => []];
}

require_once 'partials/head.php';
?>
<div class="flex-1 overflow-hidden">
<div class="container mx-auto px-2 max-w-full h-full">

    <?php require_once 'components/player.php'; ?>

    <?php require_once 'components/browser.php'; ?>

    <?php if ($error): ?>
    <div class="app-container rounded-lg mb-6">
        <div class="bg-red-900 bg-opacity-30 border border-red-500 rounded-lg p-4 m-6">
            <h3 class="text-red-400 font-semibold mb-2"><i class="fas fa-times-circle"></i> System Error</h3>
            <p class="text-red-300"><?= htmlspecialchars($error) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- <div class="app-container rounded-lg mb-6"> -->
        <!-- <div class="app-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <i class="fas fa-compact-disc text-4xl text-cyan-400 animate-spin" style="animation-duration: 10s;"></i>
                    <div>
                        <h1 class="text-3xl font-bold deck-title">Rekordbox Export Reader</h1>
                        <p class="text-gray-400 mt-1 text-sm">Professional DJ Library Manager - Powered by PHP</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/table" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-table"></i> Tables
                    </a>
                    <a href="/debug" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-chart-line"></i> Debug
                    </a>
                    <div class="text-right">
                        <div class="text-sm text-cyan-400 font-mono">v2.1</div>
                    </div>
                </div>
            </div>
        </div> -->
    <!-- </div> -->
</div>
</div>
<?php require_once 'partials/footer.php'; ?>
