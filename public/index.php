<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide errors in production

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekordbox Export Reader - PHP Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        .track-row:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Rekordbox Export Reader</h1>
                    <p class="text-gray-600 mt-2">PHP Edition - Membaca Library Rekordbox dari USB/SD Export</p>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h3 class="text-red-800 font-semibold mb-2">❌ Error</h3>
                <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($stats): ?>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold"><?= $stats['total_tracks'] ?></div>
                    <div class="text-blue-100 text-sm">Total Tracks</div>
                </div>
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold"><?= $stats['total_playlists'] ?></div>
                    <div class="text-green-100 text-sm">Total Playlists</div>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold"><?= $stats['valid_playlists'] ?></div>
                    <div class="text-purple-100 text-sm">Valid Playlists</div>
                </div>
                <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold"><?= $stats['corrupt_playlists'] ?></div>
                    <div class="text-yellow-100 text-sm">Corrupt Playlists</div>
                </div>
                <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold"><?= $stats['processing_time'] ?>s</div>
                    <div class="text-indigo-100 text-sm">Processing Time</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($data): ?>
        <div class="bg-white rounded-lg shadow-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button onclick="showTab('tracks')" id="tracksTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                        🎵 Tracks (<?= count($data['tracks']) ?>)
                    </button>
                    <button onclick="showTab('playlists')" id="playlistsTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        📋 Playlists (<?= count($data['playlists']) ?>)
                    </button>
                    <button onclick="showTab('metadata')" id="metadataTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        ℹ️ Metadata
                    </button>
                </nav>
            </div>

            <div id="tracksContent" class="tab-content p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-800">Track List</h2>
                    <input 
                        type="text" 
                        id="searchTracks" 
                        placeholder="🔍 Search tracks..."
                        onkeyup="filterTracks()"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                </div>
                
                <?php if (count($data['tracks']) > 0): ?>
                <div class="table-container border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Artist</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Album</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">BPM</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Key</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Duration</th>
                            </tr>
                        </thead>
                        <tbody id="tracksTable" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($data['tracks'] as $track): ?>
                            <tr class="track-row" data-search="<?= htmlspecialchars(strtolower($track['title'] . ' ' . $track['artist'] . ' ' . $track['album'] . ' ' . $track['key'])) ?>">
                                <td class="px-4 py-3 text-sm text-gray-600"><?= $track['id'] ?></td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($track['title']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($track['artist']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($track['album']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= number_format($track['bpm'], 2) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($track['key']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= sprintf('%d:%02d', floor($track['duration'] / 60), $track['duration'] % 60) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500">
                    <p class="text-lg">No tracks found in database</p>
                    <p class="text-sm mt-2">The database may be empty or corrupted</p>
                </div>
                <?php endif; ?>
            </div>

            <div id="playlistsContent" class="tab-content p-6 hidden">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Playlists</h2>
                <?php if (count($data['playlists']) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($data['playlists'] as $playlist): ?>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="text-2xl"><?= $playlist['is_folder'] ? '📁' : '🎵' ?></span>
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($playlist['name']) ?></h3>
                                    <p class="text-sm text-gray-500"><?= $playlist['track_count'] ?> tracks</p>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500">ID: <?= $playlist['id'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500">
                    <p class="text-lg">No playlists found in database</p>
                </div>
                <?php endif; ?>
            </div>

            <div id="metadataContent" class="tab-content p-6 hidden">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Database Metadata</h2>
                <div class="bg-gray-50 rounded-lg p-4">
                    <pre class="text-sm text-gray-700 overflow-x-auto"><?= json_encode($data['metadata'], JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            document.getElementById(tabName + 'Content').classList.remove('hidden');
            document.getElementById(tabName + 'Tab').classList.remove('border-transparent', 'text-gray-500');
            document.getElementById(tabName + 'Tab').classList.add('border-blue-500', 'text-blue-600');
        }

        function filterTracks() {
            const searchTerm = document.getElementById('searchTracks').value.toLowerCase();
            const rows = document.querySelectorAll('.track-row');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
