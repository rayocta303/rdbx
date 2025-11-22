<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

$data = null;
$stats = null;
$error = null;

try {
    $exportPath = __DIR__ . '/../../Rekordbox-USB';
    
    if (is_dir($exportPath)) {
        $reader = new RekordboxReader($exportPath, __DIR__ . '/../../output', false);
        $data = $reader->run();
        $stats = $reader->getStats();
    } else {
        $error = "Rekordbox-USB directory not found!";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/../partials/head.php';
?>

<div class="container mx-auto px-2 max-w-full">
    <div class="mixxx-container rounded-lg mb-6">
        <div class="mixxx-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <i class="fas fa-chart-bar text-4xl text-cyan-400"></i>
                    <div>
                        <h1 class="text-3xl font-bold deck-title">Statistics</h1>
                        <p class="text-gray-400 mt-1 text-sm">Library Statistics & Analysis</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="bg-red-900 bg-opacity-30 border border-red-500 rounded-lg p-4 m-6">
            <h3 class="text-red-400 font-semibold mb-2"><i class="fas fa-times-circle"></i> System Error</h3>
            <p class="text-red-300"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($stats): ?>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 p-6">
            <div class="stat-card group">
                <div class="flex items-center gap-3">
                    <i class="fas fa-music text-2xl text-cyan-400 group-hover:scale-110 transition-transform"></i>
                    <div>
                        <div class="text-2xl font-bold text-cyan-400"><?= $stats['total_tracks'] ?></div>
                        <div class="text-gray-400 text-xs uppercase tracking-wide">Tracks</div>
                    </div>
                </div>
            </div>
            <div class="stat-card group">
                <div class="flex items-center gap-3">
                    <i class="fas fa-list text-2xl text-green-400 group-hover:scale-110 transition-transform"></i>
                    <div>
                        <div class="text-2xl font-bold text-green-400"><?= $stats['total_playlists'] ?></div>
                        <div class="text-gray-400 text-xs uppercase tracking-wide">Playlists</div>
                    </div>
                </div>
            </div>
            <div class="stat-card group">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-2xl text-purple-400 group-hover:scale-110 transition-transform"></i>
                    <div>
                        <div class="text-2xl font-bold text-purple-400"><?= $stats['valid_playlists'] ?></div>
                        <div class="text-gray-400 text-xs uppercase tracking-wide">Valid</div>
                    </div>
                </div>
            </div>
            <div class="stat-card group">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-2xl text-yellow-400 group-hover:scale-110 transition-transform"></i>
                    <div>
                        <div class="text-2xl font-bold text-yellow-400"><?= $stats['corrupt_playlists'] ?></div>
                        <div class="text-gray-400 text-xs uppercase tracking-wide">Corrupt</div>
                    </div>
                </div>
            </div>
            <div class="stat-card group">
                <div class="flex items-center gap-3">
                    <i class="fas fa-clock text-2xl text-orange-400 group-hover:scale-110 transition-transform"></i>
                    <div>
                        <div class="text-2xl font-bold text-orange-400"><?= $stats['processing_time'] ?>s</div>
                        <div class="text-gray-400 text-xs uppercase tracking-wide">Parse Time</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
