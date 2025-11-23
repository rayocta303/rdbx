<?php if ($data): ?>
<div class="app-container rounded-lg mb-6">
    <div class="flex gap-0" style="height: 330px;">
        <div class="w-64 library-panel overflow-y-auto flex-shrink-0 scrollbar-thin">
            <div class="flex content-center h-8 px-4 bg-gradient-to-r from-cyan-900 to-cyan-800 border-b border-gray-800">
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
                    <i class="fas fa-list mr-2"></i> All Tracks 
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
                                echo 'class="playlist-item w-full text-left px-3 py-2 rounded mb-1" ';
                                echo 'data-track-ids="' . htmlspecialchars(json_encode($playlist['entries'])) . '">';
                                echo '<div class="flex items-center justify-between">';
                                echo '<span class="truncate">' . $indent . '<i class="fas fa-music mr-2"></i> ' . htmlspecialchars($playlist['name']) . '</span>';
                                echo '<span class="text-xs opacity-60 ml-2 playlist-counter" data-count="' . $playlist['track_count'] . '">' . $playlist['track_count'] . '</span>';
                                echo '</div>';
                                echo '</button>';
                            }
                        }
                    }
                    
                    $playlistTree = buildPlaylistTree($data['playlists']);
                    renderPlaylistTree($playlistTree);
                    ?>
                <?php endif; ?>
                
                <div class="mt-3 pt-3 border-t border-gray-700">
                    <button 
                        onclick="browseUSBDrive()" 
                        id="usb_drive_browser"
                        class="playlist-item w-full text-left px-3 py-2 rounded mb-1 bg-gradient-to-r from-purple-900/30 to-blue-900/30 hover:from-purple-800/40 hover:to-blue-800/40 border border-purple-700/50">
                        <i class="fas fa-usb mr-2"></i> Browse USB Drive (Client)
                        <span class="text-xs ml-1 opacity-70 block mt-1">Access your local USB drive directly</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col overflow-hidden bg-black/90 min-w-0">
            <div class="playlist-panel px-4 border-b-2 border-gray-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold deck-title flex items-center gap-2 flex-shrink-0" id="currentPlaylistTitle">
                        <i class="fas fa-headphones"></i>
                        <span>All Tracks</span>
                    </h2>
                    <div class="flex items-center flex-shrink-0">
                        <button 
                            id="toggleDetailPanel" 
                            onclick="toggleTrackDetailPanel()"
                            class="panel-btn px-4 h-8 transition-all flex items-center"
                            title="Toggle Detail Panel">
                            <i class="fas fa-info-circle"></i>
                            <span id="toggleDetailPanelText" class="hidden">Hide Details</span>
                        </button>
                        <div class="relative w-80">
                          <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                          <input 
                            type="text" 
                            id="searchTracks" 
                            placeholder="Search tracks, artists, or genres..."
                            onkeyup="filterTracks()"
                            class="pl-10 pr-4 w-full h-8 search-input">
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
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider w-12">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[200px]">
                                    <i class="fas fa-music mr-1"></i>Title
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[150px]">
                                    <i class="fas fa-user mr-1"></i>Artist
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[80px]">
                                    <i class="fas fa-drum mr-1"></i>BPM
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[60px]">
                                    <i class="fas fa-key mr-1"></i>Key
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[120px]">
                                    <i class="fas fa-tag mr-1"></i>Genre
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[80px]">
                                    <i class="fas fa-map-marker-alt mr-1"></i>Cues
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider min-w-[80px]">
                                    <i class="fas fa-clock mr-1"></i>Time
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider w-32">
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

        <div id="trackDetailPanel" class="w-2/5 border-l-2 overflow-y-auto hidden flex-shrink-0 scrollbar-thin deck-section">
            <div class="p-3 bg-gradient-to-b from-gray-900 to-gray-800">
                <div class="mb-3 px-3 border border-gray-700 bg-gray-800">
                    <h2 id="detailTrackTitle" class="text-lg font-bold text-white mb-1">Track Title</h2>
                    <p id="detailTrackArtist" class="text-sm text-cyan-300">Artist Name</p>
                </div>

                <div class="grid grid-cols-6 gap-3 mb-3 p-3 bg-gray-800 bg-opacity-50 border border-gray-700">
                    <div>
                        <div class="metadata-label">BPM</div>
                        <div id="detailTrackBPM" class="bpm-indicator text-sm">120.00</div>
                    </div>
                    <div>
                        <div class="metadata-label">Key</div>
                        <div id="detailTrackKey" class="metadata-value">Am</div>
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
                    <h3 class="text-xs font-semibold mb-1.5 flex items-center gap-2">
                        <i class="fas fa-chart-area"></i>
                        <span>WAVEFORM OVERVIEW</span>
                    </h3>
                    <div class="waveform-container">
                        <canvas id="waveformOverview" class="cursor-pointer w-full"></canvas>
                    </div>
                </div>

                <div class="mb-3">
                    <h3 class="text-xs font-semibold mb-1.5 flex items-center gap-2">
                        <i class="fas fa-waveform-path"></i>
                        <span>WAVEFORM DETAILED</span>
                    </h3>
                    <div class="waveform-container">
                        <canvas id="waveformDetailed" class="cursor-pointer w-full"></canvas>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-semibold mb-2 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>HOT CUES & MEMORY POINTS</span>
                    </h3>
                    <div id="cueListContainer" class="bg-gray-900 bg-opacity-50 p-2 border border-gray-700">
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
