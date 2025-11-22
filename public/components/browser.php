<?php if ($data): ?>
<div class="app-container rounded-lg mb-6">
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

        <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 min-w-0">
            <div class="p-4 border-b-2 border-cyan-600 bg-gradient-to-r from-gray-800 to-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-xl font-bold deck-title flex items-center gap-2 flex-shrink-0" id="currentPlaylistTitle">
                        <i class="fas fa-headphones"></i>
                        <span>All Tracks</span>
                    </h2>
                    <div class="flex items-center gap-3 flex-shrink-0">
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
            
            <div class="flex-1 overflow-x-auto overflow-y-auto custom-scrollbar">
                <?php if (count($data['tracks']) > 0): ?>
                <div class="min-w-max">
                    <table class="w-full">
                        <thead class="table-header sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider w-12">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[200px]">
                                    <i class="fas fa-music mr-1"></i>Title
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[150px]">
                                    <i class="fas fa-user mr-1"></i>Artist
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[80px]">
                                    <i class="fas fa-drum mr-1"></i>BPM
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[60px]">
                                    <i class="fas fa-key mr-1"></i>Key
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[120px]">
                                    <i class="fas fa-tag mr-1"></i>Genre
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[80px]">
                                    <i class="fas fa-map-marker-alt mr-1"></i>Cues
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider min-w-[80px]">
                                    <i class="fas fa-clock mr-1"></i>Time
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider w-32">
                                    <i class="fas fa-play-circle mr-1"></i>Load
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tracksTable" class="divide-y divide-gray-800">
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500">
                    <i class="fas fa-music text-4xl mb-3"></i>
                    <p class="text-lg">No tracks found in database</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="trackDetailPanel" class="w-2/5 border-l-2 border-cyan-600 overflow-y-auto hidden flex-shrink-0 scrollbar-thin deck-section">
            <div class="p-3 bg-gradient-to-b from-gray-900 to-gray-800">
                <div class="mb-3 p-3 bg-gradient-to-r from-cyan-900 to-blue-900 rounded-lg border border-cyan-700">
                    <h2 id="detailTrackTitle" class="text-lg font-bold text-white mb-1">Track Title</h2>
                    <p id="detailTrackArtist" class="text-sm text-cyan-300">Artist Name</p>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3 p-3 bg-gray-800 bg-opacity-50 rounded-lg border border-gray-700">
                    <div>
                        <div class="metadata-label">BPM</div>
                        <div id="detailTrackBPM" class="bpm-indicator text-base">120.00</div>
                    </div>
                    <div>
                        <div class="metadata-label">Key</div>
                        <div id="detailTrackKey" class="metadata-value text-base">Am</div>
                    </div>
                    <div>
                        <div class="metadata-label">Genre</div>
                        <div id="detailTrackGenre" class="metadata-value text-sm">-</div>
                    </div>
                    <div>
                        <div class="metadata-label">Duration</div>
                        <div id="detailTrackDuration" class="metadata-value text-sm">0:00</div>
                    </div>
                    <div class="col-span-2">
                        <div class="metadata-label">Rating</div>
                        <div id="detailTrackRating" class="mt-0.5">-</div>
                    </div>
                </div>

                <div id="audioPlayerContainer" class="mb-3"></div>

                <div class="mb-3">
                    <h3 class="text-xs font-semibold text-cyan-400 mb-1.5 flex items-center gap-2">
                        <i class="fas fa-chart-area"></i>
                        <span>WAVEFORM OVERVIEW</span>
                    </h3>
                    <div class="waveform-container">
                        <canvas id="waveformOverview" class="cursor-pointer"></canvas>
                    </div>
                </div>

                <div class="mb-3">
                    <h3 class="text-xs font-semibold text-cyan-400 mb-1.5 flex items-center gap-2">
                        <i class="fas fa-waveform-path"></i>
                        <span>WAVEFORM DETAILED</span>
                    </h3>
                    <div class="waveform-container">
                        <canvas id="waveformDetailed" class="cursor-pointer"></canvas>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-semibold text-cyan-400 mb-2 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>HOT CUES & MEMORY POINTS</span>
                    </h3>
                    <div id="cueListContainer" class="bg-gray-900 bg-opacity-50 rounded-lg p-2 border border-gray-700">
                        <div class="text-center text-gray-500 py-3">
                            <i class="fas fa-map-marker-alt text-xl mb-1"></i>
                            <div class="text-sm">No cue points</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
