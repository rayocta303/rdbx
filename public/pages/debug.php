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
                    <i class="fas fa-bug text-4xl text-cyan-400"></i>
                    <div>
                        <h1 class="text-3xl font-bold deck-title">Debug Panel</h1>
                        <p class="text-gray-400 mt-1 text-sm">Database Metadata & Debug Information</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>

        <?php if ($data): ?>
        <div class="border-b-2 border-cyan-600">
            <nav class="flex -mb-px">
                <button onclick="showTab('metadata')" id="metadataTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-cyan-500 text-cyan-400 bg-gray-800">
                    <i class="fas fa-info-circle"></i> Database Metadata
                </button>
            </nav>
        </div>

        <div id="metadataContent" class="tab-content p-6">
            <h2 class="text-2xl font-bold deck-title mb-4 flex items-center gap-2">
                <i class="fas fa-database"></i>
                <span>Database Metadata</span>
            </h2>
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                <pre class="text-sm text-cyan-300 overflow-x-auto"><?= json_encode($data['metadata'], JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
        <?php else: ?>
        <div class="p-6">
            <div class="bg-red-900 bg-opacity-30 border border-red-500 rounded-lg p-4">
                <h3 class="text-red-400 font-semibold mb-2"><i class="fas fa-times-circle"></i> No Data Available</h3>
                <p class="text-red-300"><?= htmlspecialchars($error ?: 'No data loaded') ?></p>
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
        button.classList.remove('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
        button.classList.add('border-transparent', 'text-gray-500', 'bg-transparent');
    });

    document.getElementById(tabName + 'Content').classList.remove('hidden');
    document.getElementById(tabName + 'Tab').classList.remove('border-transparent', 'text-gray-500', 'bg-transparent');
    document.getElementById(tabName + 'Tab').classList.add('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
}
</script>
</body>
</html>
