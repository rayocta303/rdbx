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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #e0e0e0;
            min-height: 100vh;
        }
        
        .mixxx-container {
            background: #1e1e1e;
            border: 1px solid #333;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        
        .mixxx-header {
            background: linear-gradient(180deg, #2a2a2a 0%, #1e1e1e 100%);
            border-bottom: 2px solid #00d4ff;
            padding: 1rem 1.5rem;
        }
        
        .deck-section {
            background: #252525;
            border: 1px solid #3a3a3a;
            border-radius: 8px;
        }
        
        .library-panel {
            background: #1a1a1a;
            border-right: 2px solid #2a2a2a;
        }
        
        .playlist-item {
            transition: all 0.2s ease;
            color: #b0b0b0;
        }
        
        .playlist-item:hover {
            background: #2a2a2a;
            color: #00d4ff;
        }
        
        .playlist-item.active {
            background: linear-gradient(90deg, #00d4ff20 0%, transparent 100%);
            border-left: 3px solid #00d4ff;
            color: #00d4ff;
            font-weight: 600;
        }
        
        .track-row {
            background: #1e1e1e;
            border-bottom: 1px solid #2a2a2a;
            transition: all 0.15s ease;
            color: #d0d0d0;
        }
        
        .track-row:hover {
            background: #252525;
            border-left: 3px solid #00d4ff;
        }
        
        .track-row.selected {
            background: linear-gradient(90deg, #00d4ff30 0%, #252525 100%);
            border-left: 3px solid #00d4ff;
            color: #ffffff;
        }
        
        .waveform-container {
            background: #0a0a0a;
            border: 2px solid #2a2a2a;
            border-radius: 4px;
            position: relative;
        }
        
        canvas {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .cue-pad {
            background: linear-gradient(135deg, #2a2a2a 0%, #1e1e1e 100%);
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .cue-pad:hover {
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #2a2a2a 0%, #1e1e1e 100%);
            border: 1px solid #3a3a3a;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: #00d4ff;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.2);
        }
        
        .search-input {
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            color: #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            background: #303030;
            border-color: #00d4ff;
            outline: none;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.2);
        }
        
        .table-header {
            background: #1a1a1a;
            border-bottom: 2px solid #00d4ff;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .bpm-indicator {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #00ff88;
        }
        
        .key-major {
            color: #ff9500;
            font-weight: 700;
        }
        
        .key-minor {
            color: #a855f7;
            font-weight: 700;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #3a3a3a;
            border-radius: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #00d4ff;
        }
        
        .deck-title {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }
        
        .metadata-label {
            color: #888;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .metadata-value {
            color: #e0e0e0;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-6 max-w-full">
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
                        <div class="text-right">
                            <div class="text-xs text-gray-500">MIXXX EDITION</div>
                            <div class="text-sm text-cyan-400 font-mono">v2.0</div>
                        </div>
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

        <?php if ($data): ?>
        
        <div class="mixxx-container rounded-lg mb-6">
            <div class="flex gap-0" style="height: 750px;">
                <div class="w-64 library-panel overflow-y-auto flex-shrink-0 scrollbar-thin">
                    <div class="p-4 bg-gradient-to-r from-cyan-900 to-cyan-800 border-b border-cyan-600">
                        <h3 class="font-semibold text-cyan-100 flex items-center gap-2">
                            <i class="fas fa-list"></i> 
                            <span>Library</span>
                        </h3>
                    </div>
                    
                    <div class="p-2">
                        <button 
                            onclick="showAllTracks()" 
                            id="playlist_all"
                            class="playlist-item w-full text-left px-3 py-2 rounded mb-1 active">
                            <i class="fas fa-music mr-2"></i> All Tracks 
                            <span class="text-xs ml-1 opacity-70">(<?= count($data['tracks']) ?>)</span>
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
                                        echo '<button class="w-full text-left px-3 py-2 text-gray-400 hover:text-cyan-400 hover:bg-gray-800 font-semibold transition-all">';
                                        echo $indent . '<i class="fas fa-folder mr-2"></i> ' . htmlspecialchars($playlist['name']);
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
                                        echo 'class="playlist-item w-full text-left px-3 py-2 rounded mb-1">';
                                        echo '<div class="flex items-center justify-between">';
                                        echo '<span class="truncate">' . $indent . '<i class="fas fa-music mr-2"></i> ' . htmlspecialchars($playlist['name']) . '</span>';
                                        echo '<span class="text-xs opacity-60 ml-2">' . $playlist['track_count'] . '</span>';
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

                <div class="flex-1 flex flex-col overflow-hidden bg-gray-900">
                    <div class="p-4 border-b-2 border-cyan-600 bg-gradient-to-r from-gray-800 to-gray-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold deck-title flex items-center gap-2" id="currentPlaylistTitle">
                                <i class="fas fa-headphones"></i>
                                <span>All Tracks</span>
                            </h2>
                            <div class="flex items-center gap-3">
                                <button 
                                    id="toggleDetailPanel" 
                                    onclick="toggleTrackDetailPanel()"
                                    class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg transition-all flex items-center gap-2"
                                    title="Toggle Detail Panel">
                                    <i class="fas fa-info-circle"></i>
                                    <span id="toggleDetailPanelText">Hide Details</span>
                                </button>
                                <div class="relative w-80">
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500"></i>
                                    <input 
                                        type="text" 
                                        id="searchTracks" 
                                        placeholder="Search tracks, artists, or genres..."
                                        onkeyup="filterTracks()"
                                        class="pl-10 pr-4 py-2 w-full search-input rounded-lg">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto scrollbar-thin">
                        <?php if (count($data['tracks']) > 0): ?>
                        <table class="min-w-full">
                            <thead class="table-header">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider w-12">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-music mr-1"></i>Title
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-user mr-1"></i>Artist
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-drum mr-1"></i>BPM
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-key mr-1"></i>Key
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-tag mr-1"></i>Genre
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-map-marker-alt mr-1"></i>Cues
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">
                                        <i class="fas fa-clock mr-1"></i>Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tracksTable" class="divide-y divide-gray-800">
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-music text-4xl mb-3"></i>
                            <p class="text-lg">No tracks found in database</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="trackDetailPanel" class="w-2/5 border-l-2 border-cyan-600 overflow-y-auto hidden flex-shrink-0 scrollbar-thin deck-section">
                    <div class="p-6 bg-gradient-to-b from-gray-900 to-gray-800">
                        <div class="mb-6 p-4 bg-gradient-to-r from-cyan-900 to-blue-900 rounded-lg border border-cyan-700">
                            <h2 id="detailTrackTitle" class="text-2xl font-bold text-white mb-2">Track Title</h2>
                            <p id="detailTrackArtist" class="text-lg text-cyan-300">Artist Name</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6 p-4 bg-gray-800 bg-opacity-50 rounded-lg border border-gray-700">
                            <div>
                                <div class="metadata-label">BPM</div>
                                <div id="detailTrackBPM" class="bpm-indicator text-xl">120.00</div>
                            </div>
                            <div>
                                <div class="metadata-label">Key</div>
                                <div id="detailTrackKey" class="metadata-value text-xl">Am</div>
                            </div>
                            <div>
                                <div class="metadata-label">Genre</div>
                                <div id="detailTrackGenre" class="metadata-value">-</div>
                            </div>
                            <div>
                                <div class="metadata-label">Duration</div>
                                <div id="detailTrackDuration" class="metadata-value">0:00</div>
                            </div>
                            <div class="col-span-2">
                                <div class="metadata-label">Rating</div>
                                <div id="detailTrackRating" class="mt-1">-</div>
                            </div>
                        </div>

                        <div id="audioPlayerContainer" class="mb-6"></div>

                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-cyan-400 mb-2 flex items-center gap-2">
                                <i class="fas fa-chart-area"></i>
                                <span>WAVEFORM OVERVIEW</span>
                            </h3>
                            <div class="waveform-container">
                                <canvas id="waveformOverview" class="cursor-pointer"></canvas>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-cyan-400 mb-2 flex items-center gap-2">
                                <i class="fas fa-waveform-path"></i>
                                <span>WAVEFORM DETAILED</span>
                            </h3>
                            <div class="waveform-container">
                                <canvas id="waveformDetailed" class="cursor-pointer"></canvas>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-cyan-400 mb-3 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>HOT CUES & MEMORY POINTS</span>
                            </h3>
                            <div id="cueListContainer" class="bg-gray-900 bg-opacity-50 rounded-lg p-3 border border-gray-700">
                                <div class="text-center text-gray-500 py-4">
                                    <i class="fas fa-map-marker-alt text-2xl mb-2"></i>
                                    <div>No cue points</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mixxx-container rounded-lg mb-6">
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
                button.classList.remove('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
                button.classList.add('border-transparent', 'text-gray-500', 'bg-transparent');
            });

            document.getElementById(tabName + 'Content').classList.remove('hidden');
            document.getElementById(tabName + 'Tab').classList.remove('border-transparent', 'text-gray-500', 'bg-transparent');
            document.getElementById(tabName + 'Tab').classList.add('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
        }

        function showAllTracks() {
            currentPlaylistId = 'all';
            document.getElementById('currentPlaylistTitle').innerHTML = '<i class="fas fa-headphones"></i><span>All Tracks</span>';
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById('playlist_all').classList.add('active');
            
            renderTracks(tracksData);
        }
        
        function showPlaylist(playlistId) {
            currentPlaylistId = playlistId;
            
            const playlist = playlistsData.find(p => p.id == playlistId);
            if (!playlist) return;
            
            document.getElementById('currentPlaylistTitle').innerHTML = '<i class="fas fa-list"></i><span>' + escapeHtml(playlist.name) + '</span>';
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const btn = document.getElementById('playlist_' + playlistId);
            if (btn) {
                btn.classList.add('active');
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
                const cueIcon = cueCount > 0 ? `<i class="fas fa-map-marker-alt text-orange-400"></i> ${cueCount}` : '-';
                
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm text-gray-500">${index + 1}</td>
                    <td class="px-4 py-3 text-sm font-medium text-white">${escapeHtml(track.title)}</td>
                    <td class="px-4 py-3 text-sm text-cyan-300">${escapeHtml(track.artist)}</td>
                    <td class="px-4 py-3 text-sm bpm-indicator">${track.bpm.toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm font-semibold ${getKeyColor(track.key)}">${escapeHtml(track.key)}</td>
                    <td class="px-4 py-3 text-sm text-gray-400">${escapeHtml(track.genre)}</td>
                    <td class="px-4 py-3 text-sm">${cueIcon}</td>
                    <td class="px-4 py-3 text-sm text-gray-400 font-mono">${formatDuration(track.duration)}</td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        
        function getKeyColor(key) {
            if (!key) return '';
            if (key.endsWith('A') || key.endsWith('m')) return 'key-minor';
            return 'key-major';
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

        function toggleTrackDetailPanel() {
            const panel = document.getElementById('trackDetailPanel');
            const toggleBtn = document.getElementById('toggleDetailPanel');
            const toggleText = document.getElementById('toggleDetailPanelText');
            
            if (panel.classList.contains('hidden')) {
                panel.classList.remove('hidden');
                toggleText.textContent = 'Hide Details';
            } else {
                panel.classList.add('hidden');
                toggleText.textContent = 'Show Details';
            }
        }
    </script>
</body>
</html>
