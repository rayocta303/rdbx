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
    <div class="app-container rounded-lg mb-6">
        <div class="app-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-line text-2xl text-cyan-400"></i>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Debug & Statistics</h1>
                        <p class="text-gray-400 text-xs">System Information & Library Analytics</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 rounded text-xs font-medium transition-colors border border-gray-600">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="p-4 m-4 bg-red-900/20 border border-red-700 rounded">
            <h3 class="text-red-400 font-semibold text-sm mb-1"><i class="fas fa-exclamation-circle"></i> Error</h3>
            <p class="text-red-300 text-xs"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php elseif ($data): ?>
        
        <div class="border-b border-gray-700">
            <nav class="flex px-4">
                <button onclick="showTab('stats')" id="statsTab" class="tab-button px-4 py-2.5 text-xs font-medium border-b-2 border-cyan-500 text-cyan-400 bg-gray-800/50">
                    <i class="fas fa-chart-bar"></i> Statistics
                </button>
                <button onclick="showTab('metadata')" id="metadataTab" class="tab-button px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-300">
                    <i class="fas fa-database"></i> Database Metadata
                </button>
            </nav>
        </div>

        <div id="statsContent" class="tab-content p-4">
            <?php if ($stats): ?>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                <div class="bg-gray-800 border border-gray-700 rounded p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-music text-lg text-cyan-400"></i>
                        <div>
                            <div class="text-xl font-bold text-cyan-400"><?= $stats['total_tracks'] ?></div>
                            <div class="text-gray-500 text-xs uppercase">Tracks</div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-800 border border-gray-700 rounded p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-list text-lg text-green-400"></i>
                        <div>
                            <div class="text-xl font-bold text-green-400"><?= $stats['total_playlists'] ?></div>
                            <div class="text-gray-500 text-xs uppercase">Playlists</div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-800 border border-gray-700 rounded p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-lg text-purple-400"></i>
                        <div>
                            <div class="text-xl font-bold text-purple-400"><?= $stats['valid_playlists'] ?></div>
                            <div class="text-gray-500 text-xs uppercase">Valid</div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-800 border border-gray-700 rounded p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-lg text-yellow-400"></i>
                        <div>
                            <div class="text-xl font-bold text-yellow-400"><?= $stats['corrupt_playlists'] ?></div>
                            <div class="text-gray-500 text-xs uppercase">Corrupt</div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-800 border border-gray-700 rounded p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-lg text-orange-400"></i>
                        <div>
                            <div class="text-xl font-bold text-orange-400"><?= $stats['processing_time'] ?>s</div>
                            <div class="text-gray-500 text-xs uppercase">Parse Time</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div id="metadataContent" class="tab-content p-4 hidden">
            <h2 class="text-lg font-bold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-database text-cyan-400"></i>
                <span>Database Metadata</span>
            </h2>
            <div class="bg-gray-900 rounded border border-gray-700 p-3 overflow-auto max-h-96">
                <pre class="text-xs text-cyan-300"><?= json_encode($data['metadata'], JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-cyan-500', 'text-cyan-400', 'bg-gray-800/50');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    document.getElementById(tabName + 'Content').classList.remove('hidden');
    document.getElementById(tabName + 'Tab').classList.remove('border-transparent', 'text-gray-500');
    document.getElementById(tabName + 'Tab').classList.add('border-cyan-500', 'text-cyan-400', 'bg-gray-800/50');
}
</script>
</body>
</html>
