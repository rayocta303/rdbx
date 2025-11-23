<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../src/Utils/DatabaseCorruptor.php';
require_once __DIR__ . '/../../src/Utils/DatabaseRecovery.php';

use RekordboxReader\Utils\DatabaseCorruptor;
use RekordboxReader\Utils\DatabaseRecovery;

require_once __DIR__ . '/../partials/head.php';
?>
<style>
    .scenario-card {
        transition: all 0.3s ease;
    }
    .scenario-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .log-entry {
        font-family: 'Courier New', monospace;
        font-size: 12px;
    }
</style>
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <div class="mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <i class="fas fa-database mr-3 text-blue-400"></i>
                Database Recovery Tool
            </h1>
            <p class="text-gray-400">Test and recover corrupted Rekordbox export.pdb databases</p>
        </div>

        <!-- Database Selection -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-folder-open mr-2 text-yellow-400"></i>
                Database Selection
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Source Database</label>
                    <select id="sourceDb" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2">
                        <option value="Rekordbox-USB/PIONEER/rekordbox/export.pdb">Original (Rekordbox-USB)</option>
                        <option value="Rekordbox-USB-Corrupted/PIONEER/rekordbox/export.pdb">Corrupted Copy</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Reference Database (Optional)</label>
                    <select id="referenceDb" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2">
                        <option value="">None</option>
                        <option value="Rekordbox-USB/PIONEER/rekordbox/export.pdb">Original (for templates)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Output Path</label>
                    <input type="text" id="outputPath" value="Rekordbox-USB-Corrupted/PIONEER/rekordbox/export_recovered.pdb" 
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2">
                </div>
            </div>
        </div>

        <!-- Corruption Scenarios -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-bug mr-2 text-red-400"></i>
                Corruption Scenarios (Testing)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                $scenarios = [
                    1 => ['name' => 'Magic Header', 'icon' => 'fa-file-signature', 'color' => 'red'],
                    2 => ['name' => 'Metadata Header', 'icon' => 'fa-info-circle', 'color' => 'orange'],
                    3 => ['name' => 'Page Headers', 'icon' => 'fa-file-alt', 'color' => 'yellow'],
                    4 => ['name' => 'Row Bitmap', 'icon' => 'fa-th', 'color' => 'green'],
                    5 => ['name' => 'Table Index', 'icon' => 'fa-list', 'color' => 'teal'],
                    6 => ['name' => 'Row Structure', 'icon' => 'fa-table', 'color' => 'blue'],
                    7 => ['name' => 'Field Data', 'icon' => 'fa-font', 'color' => 'indigo'],
                    8 => ['name' => 'Playlist Structure', 'icon' => 'fa-stream', 'color' => 'purple'],
                    9 => ['name' => 'Relationships', 'icon' => 'fa-link', 'color' => 'pink'],
                    10 => ['name' => 'Version Info', 'icon' => 'fa-code-branch', 'color' => 'gray']
                ];

                foreach ($scenarios as $id => $scenario):
                ?>
                <div class="scenario-card bg-gray-700 rounded-lg p-4 border border-gray-600">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas <?= $scenario['icon'] ?> text-<?= $scenario['color'] ?>-400 mr-2"></i>
                            <span class="font-medium"><?= $id ?>. <?= $scenario['name'] ?></span>
                        </div>
                        <input type="checkbox" id="scenario<?= $id ?>" class="scenario-check w-5 h-5">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex gap-3">
                <button onclick="corruptDatabase()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Corrupt Selected
                </button>
                <button onclick="selectAll()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded">
                    Select All
                </button>
                <button onclick="deselectAll()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded">
                    Deselect All
                </button>
            </div>
        </div>

        <!-- Recovery Methods -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-wrench mr-2 text-green-400"></i>
                Recovery Methods
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button onclick="recoverSpecific(1)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-file-signature mr-2 text-red-400"></i>
                    1. Header Reconstruction
                </button>
                <button onclick="recoverSpecific(2)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-search mr-2 text-orange-400"></i>
                    2. Metadata Inference
                </button>
                <button onclick="recoverSpecific(3)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-file-alt mr-2 text-yellow-400"></i>
                    3. Page Pattern Scan
                </button>
                <button onclick="recoverSpecific(4)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-check-square mr-2 text-green-400"></i>
                    4. Force All Valid
                </button>
                <button onclick="recoverSpecific(5)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-stream mr-2 text-teal-400"></i>
                    5. Linear Full Scan
                </button>
                <button onclick="recoverSpecific(6)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-puzzle-piece mr-2 text-blue-400"></i>
                    6. Field Pattern Match
                </button>
                <button onclick="recoverSpecific(7)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-broom mr-2 text-indigo-400"></i>
                    7. Data Sanity Check
                </button>
                <button onclick="recoverSpecific(8)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-sitemap mr-2 text-purple-400"></i>
                    8. Tree Rebuild
                </button>
                <button onclick="recoverSpecific(9)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-link mr-2 text-pink-400"></i>
                    9. Orphan Relinking
                </button>
                <button onclick="recoverSpecific(10)" class="bg-gray-700 hover:bg-gray-600 p-3 rounded text-left">
                    <i class="fas fa-code-branch mr-2 text-gray-400"></i>
                    10. Version Detection
                </button>
            </div>
            <div class="mt-4">
                <button onclick="recoverAll()" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded flex items-center text-lg font-medium">
                    <i class="fas fa-magic mr-2"></i>
                    Run Full Recovery
                </button>
            </div>
        </div>

        <!-- Status & Logs -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-terminal mr-2 text-cyan-400"></i>
                Status & Logs
            </h2>
            <div id="status" class="mb-4 p-4 bg-gray-700 rounded">
                <span class="text-gray-400">Ready</span>
            </div>
            <div id="logs" class="bg-black rounded p-4 h-96 overflow-y-auto font-mono text-sm">
                <div class="text-green-400"># Database Recovery Tool initialized</div>
                <div class="text-gray-500"># Select scenarios to corrupt or recovery methods to apply</div>
            </div>
        </div>
    </div>

    <script>
        function log(message, type = 'info') {
            const logs = document.getElementById('logs');
            const colors = {
                info: 'text-cyan-400',
                success: 'text-green-400',
                error: 'text-red-400',
                warning: 'text-yellow-400'
            };
            const entry = document.createElement('div');
            entry.className = `log-entry ${colors[type]}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logs.appendChild(entry);
            logs.scrollTop = logs.scrollHeight;
        }

        function updateStatus(message, type = 'info') {
            const status = document.getElementById('status');
            const colors = {
                info: 'text-blue-400',
                success: 'text-green-400',
                error: 'text-red-400',
                processing: 'text-yellow-400'
            };
            status.innerHTML = `<span class="${colors[type]}">${message}</span>`;
        }

        function selectAll() {
            document.querySelectorAll('.scenario-check').forEach(cb => cb.checked = true);
            log('All scenarios selected');
        }

        function deselectAll() {
            document.querySelectorAll('.scenario-check').forEach(cb => cb.checked = false);
            log('All scenarios deselected');
        }

        async function corruptDatabase() {
            const selected = [];
            document.querySelectorAll('.scenario-check').forEach((cb, idx) => {
                if (cb.checked) selected.push(idx + 1);
            });

            if (selected.length === 0) {
                log('No scenarios selected', 'warning');
                return;
            }

            updateStatus('Corrupting database...', 'processing');
            log(`Corrupting with scenarios: ${selected.join(', ')}`, 'info');

            try {
                const response = await fetch('/api/recovery.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'corrupt',
                        scenarios: selected,
                        sourceDb: document.getElementById('sourceDb').value,
                        targetDb: 'Rekordbox-USB-Corrupted/PIONEER/rekordbox/export.pdb'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    log('Corruption completed successfully', 'success');
                    log(`Applied scenarios: ${result.scenarios.join(', ')}`, 'success');
                    updateStatus('Corruption completed', 'success');
                } else {
                    log(`Error: ${result.error}`, 'error');
                    updateStatus('Corruption failed', 'error');
                }
            } catch (error) {
                log(`Exception: ${error.message}`, 'error');
                updateStatus('Corruption failed', 'error');
            }
        }

        async function recoverSpecific(method) {
            updateStatus(`Running recovery method ${method}...`, 'processing');
            log(`Starting recovery method ${method}`, 'info');

            try {
                const response = await fetch('/api/recovery.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'recover',
                        method: method,
                        corruptDb: document.getElementById('sourceDb').value,
                        recoveredDb: document.getElementById('outputPath').value,
                        referenceDb: document.getElementById('referenceDb').value
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    log(`Recovery method ${method} completed`, 'success');
                    if (result.log) {
                        result.log.forEach(entry => log(entry, 'info'));
                    }
                    updateStatus('Recovery completed', 'success');
                } else {
                    log(`Error: ${result.error}`, 'error');
                    updateStatus('Recovery failed', 'error');
                }
            } catch (error) {
                log(`Exception: ${error.message}`, 'error');
                updateStatus('Recovery failed', 'error');
            }
        }

        async function recoverAll() {
            updateStatus('Running full recovery...', 'processing');
            log('Starting full recovery process', 'info');

            try {
                const response = await fetch('/api/recovery.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'recover_all',
                        corruptDb: document.getElementById('sourceDb').value,
                        recoveredDb: document.getElementById('outputPath').value,
                        referenceDb: document.getElementById('referenceDb').value
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    log('Full recovery completed successfully', 'success');
                    if (result.log) {
                        result.log.forEach(entry => log(entry, 'info'));
                    }
                    if (result.stats) {
                        log(`Statistics: ${JSON.stringify(result.stats)}`, 'info');
                    }
                    updateStatus('Full recovery completed', 'success');
                } else {
                    log(`Error: ${result.error}`, 'error');
                    updateStatus('Recovery failed', 'error');
                }
            } catch (error) {
                log(`Exception: ${error.message}`, 'error');
                updateStatus('Recovery failed', 'error');
            }
        }
    </script>

<script>
// Override track detail initialization to prevent errors on recovery page
window.toggleTrackDetailPanel = function() {};
window.loadTrackToDeck = function() {};
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
