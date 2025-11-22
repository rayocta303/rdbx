<?php
error_reporting(0);
ini_set('display_errors', 0);

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
        $data = $reader->run();
        $stats = $reader->getStats();
    } else {
        $error = "Rekordbox-USB directory not found!";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once 'partials/head.php';
?>

<div class="container mx-auto px-2 max-w-full">

    <?php require_once 'components/player.php'; ?>

    <?php require_once 'components/browser.php'; ?>

    <?php if ($error): ?>
    <div class="mixxx-container rounded-lg mb-6">
        <div class="bg-red-900 bg-opacity-30 border border-red-500 rounded-lg p-4 m-6">
            <h3 class="text-red-400 font-semibold mb-2"><i class="fas fa-times-circle"></i> System Error</h3>
            <p class="text-red-300"><?= htmlspecialchars($error) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="mixxx-container rounded-lg mb-6">
        <div class="mixxx-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <i class="fas fa-compact-disc text-4xl text-cyan-400 animate-spin" style="animation-duration: 10s;"></i>
                    <div>
                        <h1 class="text-3xl font-bold deck-title">Rekordbox Export Reader</h1>
                        <p class="text-gray-400 mt-1 text-sm">Professional DJ Library Manager - Powered by PHP</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/stats" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </a>
                    <a href="/debug" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-bug"></i> Debug
                    </a>
                    <div class="text-right">
                        <div class="text-xs text-gray-500">MIXXX EDITION</div>
                        <div class="text-sm text-cyan-400 font-mono">v2.0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
