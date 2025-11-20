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
        .track-row.selected {
            background-color: #dbeafe;
        }
        canvas {
            width: 100%;
            height: auto;
            display: block;
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
            <div class="flex gap-4" style="height: 700px;">
                <div class="w-64 border-r border-gray-200 overflow-y-auto flex-shrink-0">
                    <div class="p-4 bg-gray-100 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-700">📋 Playlists</h3>
                    </div>
                    
                    <div class="p-2">
                        <button 
                            onclick="showAllTracks()" 
                            id="playlist_all"
                            class="playlist-item w-full text-left px-3 py-2 rounded hover:bg-blue-50 mb-1 bg-blue-100 text-blue-700 font-medium">
                            🎵 All Tracks (<?= count($data['tracks']) ?>)
                        </button>
                        
                        <?php if (count($data['playlists']) > 0): ?>
                            <?php
                            function buildPlaylistTree($playlists) {
                                $tree = [];
                                $byId = [];
                                
                                foreach ($playlists as $playlist) {
                                    $byId[$playlist['id']] = $playlist;
                                    $byId[$playlist['id']]['children'] = [];
                                }
                                
                                foreach ($playlists as $playlist) {
                                    if ($playlist['parent_id'] == 0) {
                                        $tree[] = &$byId[$playlist['id']];
                                    } else if (isset($byId[$playlist['parent_id']])) {
                                        $byId[$playlist['parent_id']]['children'][] = &$byId[$playlist['id']];
                                    }
                                }
                                
                                return $tree;
                            }
                            
                            function renderPlaylistTree($playlists, $level = 0) {
                                foreach ($playlists as $playlist) {
                                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
                                    
                                    if ($playlist['is_folder']) {
                                        echo '<div class="folder-item">';
                                        echo '<button class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-100 font-semibold">';
                                        echo $indent . '📁 ' . htmlspecialchars($playlist['name']);
                                        echo '</button>';
                                        if (!empty($playlist['children'])) {
                                            echo '<div class="ml-2">';
                                            renderPlaylistTree($playlist['children'], $level + 1);
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<button onclick="showPlaylist(' . $playlist['id'] . ')" ';
                                        echo 'id="playlist_' . $playlist['id'] . '" ';
                                        echo 'class="playlist-item w-full text-left px-3 py-2 rounded hover:bg-blue-50 mb-1 text-gray-700">';
                                        echo '<div class="flex items-center justify-between">';
                                        echo '<span class="truncate">' . $indent . '🎵 ' . htmlspecialchars($playlist['name']) . '</span>';
                                        echo '<span class="text-xs text-gray-500 ml-2">' . $playlist['track_count'] . '</span>';
                                        echo '</div>';
                                        echo '</button>';
                                    }
                                }
                            }
                            
                            $playlistTree = buildPlaylistTree($data['playlists']);
                            renderPlaylistTree($playlistTree);
                            ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex-1 flex flex-col overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-gray-800" id="currentPlaylistTitle">All Tracks</h2>
                            <input 
                                type="text" 
                                id="searchTracks" 
                                placeholder="🔍 Search tracks..."
                                onkeyup="filterTracks()"
                                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto">
                        <?php if (count($data['tracks']) > 0): ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase w-12">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Title</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Artist</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">BPM</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Key</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Genre</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cues</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Duration</th>
                                </tr>
                            </thead>
                            <tbody id="tracksTable" class="bg-white divide-y divide-gray-200">
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <p class="text-lg">No tracks found in database</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="trackDetailPanel" class="w-2/5 border-l border-gray-200 overflow-y-auto hidden flex-shrink-0">
                    <div class="p-6">
                        <div class="mb-6">
                            <h2 id="detailTrackTitle" class="text-2xl font-bold text-gray-800 mb-2">Track Title</h2>
                            <p id="detailTrackArtist" class="text-lg text-gray-600">Artist Name</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                            <div>
                                <span class="text-gray-500">BPM:</span>
                                <span id="detailTrackBPM" class="font-semibold ml-2">120.00</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Key:</span>
                                <span id="detailTrackKey" class="font-semibold ml-2">Am</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Genre:</span>
                                <span id="detailTrackGenre" class="font-semibold ml-2">-</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Duration:</span>
                                <span id="detailTrackDuration" class="font-semibold ml-2">0:00</span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-500">Rating:</span>
                                <span id="detailTrackRating" class="ml-2">-</span>
                            </div>
                        </div>

                        <div id="audioPlayerContainer" class="mb-6"></div>

                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Waveform Overview</h3>
                            <div class="bg-gray-900 rounded overflow-hidden">
                                <canvas id="waveformOverview" class="cursor-pointer"></canvas>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Waveform Detailed</h3>
                            <div class="bg-gray-900 rounded overflow-hidden">
                                <canvas id="waveformDetailed" class="cursor-pointer"></canvas>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Cue Points</h3>
                            <div id="cueListContainer" class="bg-gray-50 rounded p-3">
                                <div class="text-center text-gray-500 py-4">No cue points</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button onclick="showTab('metadata')" id="metadataTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                        ℹ️ Database Metadata
                    </button>
                </nav>
            </div>

            <div id="metadataContent" class="tab-content p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Database Metadata</h2>
                <div class="bg-gray-50 rounded-lg p-4">
                    <pre class="text-sm text-gray-700 overflow-x-auto"><?= json_encode($data['metadata'], JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="js/audio-player.js"></script>
    <script src="js/waveform-renderer.js"></script>
    <script src="js/cue-manager.js"></script>
    <script src="js/track-detail.js"></script>
    
    <script>
        <?php if ($data): ?>
        const tracksData = <?= json_encode($data['tracks']) ?>;
        const playlistsData = <?= json_encode($data['playlists']) ?>;
        <?php else: ?>
        const tracksData = [];
        const playlistsData = [];
        <?php endif; ?>
        
        let currentPlaylistId = 'all';
        let trackDetailPanel = null;
        let currentSelectedTrackRow = null;
        
        window.addEventListener('DOMContentLoaded', function() {
            trackDetailPanel = new TrackDetailPanel();
            window.trackDetailPanel = trackDetailPanel;
            showAllTracks();
        });
        
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

        function showAllTracks() {
            currentPlaylistId = 'all';
            document.getElementById('currentPlaylistTitle').textContent = 'All Tracks';
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'text-blue-700', 'font-medium');
                btn.classList.add('text-gray-700');
            });
            
            document.getElementById('playlist_all').classList.add('bg-blue-100', 'text-blue-700', 'font-medium');
            document.getElementById('playlist_all').classList.remove('text-gray-700');
            
            renderTracks(tracksData);
        }
        
        function showPlaylist(playlistId) {
            currentPlaylistId = playlistId;
            
            const playlist = playlistsData.find(p => p.id == playlistId);
            if (!playlist) return;
            
            document.getElementById('currentPlaylistTitle').textContent = playlist.name;
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'text-blue-700', 'font-medium');
                btn.classList.add('text-gray-700');
            });
            
            const btn = document.getElementById('playlist_' + playlistId);
            if (btn) {
                btn.classList.add('bg-blue-100', 'text-blue-700', 'font-medium');
                btn.classList.remove('text-gray-700');
            }
            
            const trackIds = playlist.entries || [];
            const playlistTracks = tracksData.filter(t => trackIds.includes(t.id));
            
            renderTracks(playlistTracks);
        }
        
        function renderTracks(tracks) {
            const tbody = document.getElementById('tracksTable');
            tbody.innerHTML = '';
            
            tracks.forEach((track, index) => {
                const row = document.createElement('tr');
                row.className = 'track-row hover:bg-gray-50 cursor-pointer';
                row.setAttribute('data-search', (track.title + ' ' + track.artist + ' ' + track.genre + ' ' + track.key).toLowerCase());
                row.setAttribute('data-track-id', track.id);
                row.onclick = () => {
                    if (currentSelectedTrackRow) {
                        currentSelectedTrackRow.classList.remove('selected');
                    }
                    row.classList.add('selected');
                    currentSelectedTrackRow = row;
                    
                    if (trackDetailPanel) {
                        trackDetailPanel.loadTrack(track);
                    }
                };
                
                const cueCount = track.cue_points ? track.cue_points.length : 0;
                const cueInfo = cueCount > 0 ? `${cueCount} cue${cueCount > 1 ? 's' : ''}` : '-';
                
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm text-gray-500">${index + 1}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${escapeHtml(track.title)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(track.artist)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${track.bpm.toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 font-semibold ${getKeyColor(track.key)}">${escapeHtml(track.key)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(track.genre)}</td>
                    <td class="px-4 py-3 text-sm text-blue-600">${cueInfo}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${formatDuration(track.duration)}</td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        
        function getKeyColor(key) {
            if (!key) return '';
            if (key.endsWith('A') || key.endsWith('m')) return 'text-purple-600';
            if (key.endsWith('B') || !key.endsWith('m')) return 'text-blue-600';
            return '';
        }
        
        function formatDuration(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
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
