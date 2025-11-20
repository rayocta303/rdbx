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
            <div class="flex" style="height: 600px;">
                <div class="w-64 border-r border-gray-200 overflow-y-auto">
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

    <script>
        <?php if ($data): ?>
        const tracksData = <?= json_encode($data['tracks']) ?>;
        const playlistsData = <?= json_encode($data['playlists']) ?>;
        <?php else: ?>
        const tracksData = [];
        const playlistsData = [];
        <?php endif; ?>
        
        let currentPlaylistId = 'all';
        
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
                row.onclick = () => showTrackDetail(track);
                
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
        
        function showTrackDetail(track) {
            let detailHTML = `
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-2xl font-bold text-gray-800">${escapeHtml(track.title)}</h2>
                        <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div><span class="font-semibold">Artist:</span> ${escapeHtml(track.artist)}</div>
                        <div><span class="font-semibold">Genre:</span> ${escapeHtml(track.genre) || '-'}</div>
                        <div><span class="font-semibold">BPM:</span> ${track.bpm.toFixed(2)}</div>
                        <div><span class="font-semibold">Key:</span> <span class="${getKeyColor(track.key)}">${escapeHtml(track.key)}</span></div>
                        <div><span class="font-semibold">Duration:</span> ${formatDuration(track.duration)}</div>
                        <div><span class="font-semibold">Rating:</span> ${'⭐'.repeat(track.rating || 0)}</div>
                    </div>
            `;
            
            // Cue Points
            if (track.cue_points && track.cue_points.length > 0) {
                detailHTML += `
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">🎯 Cue Points (${track.cue_points.length})</h3>
                        <div class="bg-gray-50 rounded p-4">
                            ${track.cue_points.map((cue, idx) => `
                                <div class="flex items-center justify-between py-2 border-b border-gray-200">
                                    <div>
                                        <span class="font-semibold">${cue.hot_cue > 0 ? 'Hot Cue ' + String.fromCharCode(64 + cue.hot_cue) : 'Memory Cue ' + (idx + 1)}</span>
                                        <span class="text-sm text-gray-600 ml-2">(${cue.type})</span>
                                    </div>
                                    <div class="text-sm">
                                        <span>${formatTime(cue.time)}</span>
                                        ${cue.loop_time ? ` → ${formatTime(cue.loop_time)}` : ''}
                                        ${cue.comment ? `<span class="ml-2 italic text-gray-600">"${escapeHtml(cue.comment)}"</span>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            // Waveform
            if (track.waveform) {
                detailHTML += `
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">🌊 Waveform</h3>
                        <div class="bg-gray-900 rounded p-4">
                            <canvas id="waveformCanvas" width="800" height="100" class="w-full"></canvas>
                        </div>
                    </div>
                `;
            }
            
            detailHTML += `</div>`;
            
            const modal = document.createElement('div');
            modal.id = 'trackModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = detailHTML;
            modal.onclick = (e) => {
                if (e.target === modal) closeModal();
            };
            
            document.body.appendChild(modal);
            
            // Draw waveform if available
            if (track.waveform && track.waveform.preview_data) {
                setTimeout(() => drawWaveform(track.waveform.preview_data), 10);
            } else if (track.waveform && track.waveform.color_data) {
                setTimeout(() => drawColorWaveform(track.waveform.color_data), 10);
            }
        }
        
        function closeModal() {
            const modal = document.getElementById('trackModal');
            if (modal) modal.remove();
        }
        
        function formatTime(ms) {
            const totalSecs = Math.floor(ms / 1000);
            const mins = Math.floor(totalSecs / 60);
            const secs = totalSecs % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        function drawWaveform(waveData) {
            const canvas = document.getElementById('waveformCanvas');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            
            ctx.fillStyle = '#1a1a1a';
            ctx.fillRect(0, 0, width, height);
            
            const step = width / waveData.length;
            
            ctx.fillStyle = '#00D9FF';
            waveData.forEach((sample, i) => {
                const x = i * step;
                const barHeight = (sample.height / 255) * height;
                const y = (height - barHeight) / 2;
                ctx.fillRect(x, y, Math.max(1, step), barHeight);
            });
        }
        
        function drawColorWaveform(waveData) {
            const canvas = document.getElementById('waveformCanvas');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            
            ctx.fillStyle = '#1a1a1a';
            ctx.fillRect(0, 0, width, height);
            
            const step = width / waveData.length;
            
            waveData.forEach((sample, i) => {
                const x = i * step;
                const barHeight = (sample.height / 255) * height;
                const y = (height - barHeight) / 2;
                ctx.fillStyle = `rgb(${sample.r}, ${sample.g}, ${sample.b})`;
                ctx.fillRect(x, y, Math.max(1, step), barHeight);
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
        
        showAllTracks();
    </script>
</body>
</html>
