    <script src="js/audio-player.js?v=<?= time() ?>"></script>
    <script src="js/waveform-renderer.js?v=<?= time() ?>"></script>
    <script src="js/cue-manager.js?v=<?= time() ?>"></script>
    <script src="js/track-detail.js?v=<?= time() ?>"></script>
    <script src="js/dual-player.js?v=<?= time() ?>"></script>
    
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
        let usbTracksData = [];
        
        window.addEventListener('DOMContentLoaded', function() {
            trackDetailPanel = new TrackDetailPanel();
            window.trackDetailPanel = trackDetailPanel;
            
            // Update playlist counters to show only valid tracks
            document.querySelectorAll('.playlist-counter').forEach(counter => {
                const btn = counter.closest('button[data-track-ids]');
                if (btn) {
                    const trackIds = JSON.parse(btn.getAttribute('data-track-ids'));
                    const validTracks = trackIds.filter(id => tracksData.find(t => t.id === id));
                    const validCount = validTracks.length;
                    const totalCount = trackIds.length;
                    
                    if (validCount < totalCount) {
                        counter.innerHTML = validCount + ' <span class="text-orange-400 text-xs" title="' + (totalCount - validCount) + ' track tidak ditemukan">⚠</span>';
                    } else {
                        counter.textContent = validCount;
                    }
                }
            });
            
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

        window.showAllTracks = function() {
            currentPlaylistId = 'all';
            document.getElementById('currentPlaylistTitle').innerHTML = '<i class="fas fa-headphones"></i><span>All Tracks</span>';
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById('playlist_all').classList.add('active');
            
            renderTracks(tracksData);
        }
        
        window.showPlaylist = function(playlistId) {
            currentPlaylistId = playlistId;
            
            const playlist = playlistsData.find(p => p.id == playlistId);
            if (!playlist) return;
            
            const trackIds = playlist.entries || [];
            const playlistTracks = tracksData.filter(t => trackIds.includes(t.id));
            
            // Update jumlah track yang valid
            const validCount = playlistTracks.length;
            const totalCount = trackIds.length;
            
            // Tampilkan peringatan jika ada track yang hilang
            const countDisplay = validCount < totalCount 
                ? `${validCount} <span class="text-orange-400 text-xs" title="${totalCount - validCount} track tidak ditemukan di database">⚠</span>` 
                : validCount;
            
            document.getElementById('currentPlaylistTitle').innerHTML = 
                '<i class="fas fa-list"></i><span>' + escapeHtml(playlist.name) + 
                ' <span class="text-xs text-gray-500">(' + countDisplay + ')</span></span>';
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const btn = document.getElementById('playlist_' + playlistId);
            if (btn) {
                btn.classList.add('active');
            }
            
            renderTracks(playlistTracks);
        }
        
        function renderTracks(tracks) {
            const tbody = document.getElementById('tracksTable');
            tbody.innerHTML = '';
            
            tracks.forEach((track, index) => {
                const row = document.createElement('tr');
                row.className = 'track-row cursor-pointer';
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
                
                const hotCueLabels = track.cue_points ? 
                    track.cue_points
                        .filter(cue => cue.hot_cue_label)
                        .map(cue => cue.hot_cue_label)
                        .join(' ') : '';
                const cueIcon = hotCueLabels ? `<span class="text-orange-400 font-semibold">${hotCueLabels}</span>` : '-';
                
                row.innerHTML = `
                    <td class="px-2 py-1 text-sm text-gray-500">${index + 1}</td>
                    <td class="px-2 py-1 text-sm font-medium text-white">${escapeHtml(track.title)}</td>
                    <td class="px-2 py-1 text-sm text-cyan-300">${escapeHtml(track.artist)}</td>
                    <td class="px-2 py-1 text-sm bpm-indicator">${track.bpm.toFixed(2)}</td>
                    <td class="px-2 py-1 text-sm font-semibold ${getKeyColor(track.key)}">${escapeHtml(track.key)}</td>
                    <td class="px-2 py-1 text-sm text-gray-400">${escapeHtml(track.genre)}</td>
                    <td class="px-2 py-1 text-sm">${cueIcon}</td>
                    <td class="px-2 py-1 text-sm text-gray-400 font-mono">${formatDuration(track.duration)}</td>
                    <td class="px-2 py-1 text-sm">
                        <div class="flex gap-1">
                            <button onclick="event.stopPropagation(); loadTrackToDeck(${track.id}, 'a')" 
                                    class="load-deck-btn load-deck-a" title="Load to Deck A">
                                A
                            </button>
                            <button onclick="event.stopPropagation(); loadTrackToDeck(${track.id}, 'b')" 
                                    class="load-deck-btn load-deck-b" title="Load to Deck B">
                                B
                            </button>
                        </div>
                    </td>
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

        window.filterTracks = function() {
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

        window.toggleTrackDetailPanel = function() {
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

        window.loadTrackToDeck = function(trackId, deckId) {
            let track = tracksData.find(t => t.id == trackId);
            if (track && window.dualPlayer) {
                window.dualPlayer.loadTrack(track, deckId);
            }
        }

        window.browseUSBDrive = async function() {
            try {
                if (!window.showDirectoryPicker) {
                    alert('File System Access API tidak didukung di browser Anda.\n\nSilakan gunakan browser modern seperti:\n- Chrome/Edge 86+\n- Opera 72+\n\nFirefox dan Safari belum mendukung fitur ini.');
                    return;
                }

                const dirHandle = await window.showDirectoryPicker({
                    mode: 'read',
                    startIn: 'downloads'
                });

                console.log('[USB Drive Browser] Directory selected:', dirHandle.name);
                
                document.getElementById('currentPlaylistTitle').innerHTML = 
                    '<i class="fas fa-spinner fa-spin"></i><span>Checking for Rekordbox database...</span>';
                
                let pdbFile = null;
                try {
                    const pioneerHandle = await dirHandle.getDirectoryHandle('PIONEER');
                    const rekordboxHandle = await pioneerHandle.getDirectoryHandle('rekordbox');
                    const pdbFileHandle = await rekordboxHandle.getFileHandle('export.pdb');
                    pdbFile = await pdbFileHandle.getFile();
                    console.log('[USB Drive] Found export.pdb, parsing database...');
                } catch (e) {
                    console.log('[USB Drive] No Rekordbox database found, scanning for audio files only');
                }
                
                if (pdbFile) {
                    await parseUSBRekordboxDatabase(pdbFile, dirHandle);
                    return;
                }
                
                const usbTracks = [];
                let trackId = 10000;

                async function scanDirectory(dirHandle, basePath = '') {
                    for await (const entry of dirHandle.values()) {
                        const fullPath = basePath ? `${basePath}/${entry.name}` : entry.name;
                        
                        if (entry.kind === 'directory') {
                            await scanDirectory(entry, fullPath);
                        } else if (entry.kind === 'file') {
                            const fileName = entry.name.toLowerCase();
                            if (!fileName.endsWith('.mp3') && !fileName.endsWith('.m4a') && !fileName.endsWith('.wav')) {
                                continue;
                            }
                            
                            try {
                                const file = await entry.getFile();
                                
                                if (file.size > 100 * 1024 * 1024) {
                                    console.warn('[USB Drive] File too large (>100MB), skipping:', entry.name);
                                    continue;
                                }
                                
                                const pathParts = fullPath.split('/');
                                let artist = 'Unknown Artist';
                                let album = 'Unknown Album';
                                let title = entry.name.replace('.mp3', '');
                                
                                if (pathParts.length >= 3) {
                                    artist = pathParts[pathParts.length - 3];
                                    album = pathParts[pathParts.length - 2];
                                }
                                
                                const blobURL = URL.createObjectURL(file);
                                
                                usbTracks.push({
                                    id: trackId++,
                                    title: title,
                                    artist: artist,
                                    album: album,
                                    label: '',
                                    key: '-',
                                    genre: 'USB Drive',
                                    bpm: 0,
                                    duration: 0,
                                    year: 0,
                                    rating: 0,
                                    file_path: blobURL,
                                    file_handle: entry,
                                    analyze_path: '',
                                    play_count: 0,
                                    comment: 'From USB Drive',
                                    cue_points: [],
                                    _usb_source: true
                                });
                                
                                console.log('[USB Drive] Found track:', title);
                            } catch (error) {
                                console.warn('[USB Drive] Failed to read file:', entry.name, error);
                            }
                        }
                    }
                }

                document.getElementById('currentPlaylistTitle').innerHTML = 
                    '<i class="fas fa-spinner fa-spin"></i><span>Scanning USB Drive...</span>';
                
                await scanDirectory(dirHandle);

                currentPlaylistId = 'usb_drive';
                document.getElementById('currentPlaylistTitle').innerHTML = 
                    '<i class="fas fa-usb"></i><span>USB Drive: ' + escapeHtml(dirHandle.name) + 
                    ' <span class="text-xs text-gray-500">(' + usbTracks.length + ' tracks)</span></span>';

                document.querySelectorAll('.playlist-item').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.getElementById('usb_drive_browser').classList.add('active');

                usbTracksData = usbTracks;
                renderTracks(usbTracks);
                
                console.log('[USB Drive Browser] Loaded', usbTracks.length, 'tracks from', dirHandle.name);
                
                if (window.dualPlayer) {
                    window.dualPlayer.showNotification(
                        `USB Drive: ${usbTracks.length} tracks loaded from ${dirHandle.name}`,
                        'success',
                        4000
                    );
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('[USB Drive Browser] User cancelled directory selection');
                } else {
                    console.error('[USB Drive Browser] Error:', error);
                    alert('Gagal membaca USB Drive:\n\n' + error.message);
                }
            }
        }

        async function parseUSBRekordboxDatabase(pdbFile, usbRootHandle) {
            try {
                const formData = new FormData();
                formData.append('export_pdb', pdbFile);
                
                document.getElementById('currentPlaylistTitle').innerHTML = 
                    '<i class="fas fa-spinner fa-spin"></i><span>Parsing Rekordbox database...</span>';
                
                const response = await fetch('/api/usb-parse.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Failed to parse database: ' + response.statusText);
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Failed to parse database');
                }
                
                console.log('[USB Drive] Database parsed successfully:', result.tracks.length, 'tracks,', result.playlists.length, 'playlists');
                
                const usbTracks = [];
                const fileHandleCache = new Map();
                
                async function findAudioFile(filePath) {
                    if (fileHandleCache.has(filePath)) {
                        return fileHandleCache.get(filePath);
                    }
                    
                    let cleanPath = filePath;
                    if (cleanPath.startsWith('/')) {
                        cleanPath = cleanPath.substring(1);
                    }
                    
                    const pathParts = cleanPath.split('/').filter(p => p && p !== '.');
                    const fileName = pathParts[pathParts.length - 1];
                    
                    try {
                        let currentHandle = usbRootHandle;
                        
                        for (let i = 0; i < pathParts.length - 1; i++) {
                            currentHandle = await currentHandle.getDirectoryHandle(pathParts[i]);
                        }
                        
                        const fileHandle = await currentHandle.getFileHandle(fileName);
                        const file = await fileHandle.getFile();
                        const blobURL = URL.createObjectURL(file);
                        
                        fileHandleCache.set(filePath, blobURL);
                        return blobURL;
                    } catch (e) {
                        console.warn('[USB Drive] Could not find file at expected path:', filePath);
                        
                        try {
                            console.log('[USB Drive] Searching for file:', fileName);
                            const foundFile = await searchFileRecursively(usbRootHandle, fileName);
                            if (foundFile) {
                                const blobURL = URL.createObjectURL(foundFile);
                                fileHandleCache.set(filePath, blobURL);
                                return blobURL;
                            }
                        } catch (searchError) {
                            console.warn('[USB Drive] Recursive search failed:', searchError);
                        }
                        
                        return null;
                    }
                }
                
                async function searchFileRecursively(dirHandle, targetFileName, maxDepth = 5, currentDepth = 0) {
                    if (currentDepth >= maxDepth) return null;
                    
                    try {
                        for await (const entry of dirHandle.values()) {
                            if (entry.kind === 'file' && entry.name === targetFileName) {
                                const fileHandle = await dirHandle.getFileHandle(entry.name);
                                return await fileHandle.getFile();
                            } else if (entry.kind === 'directory') {
                                const result = await searchFileRecursively(
                                    await dirHandle.getDirectoryHandle(entry.name),
                                    targetFileName,
                                    maxDepth,
                                    currentDepth + 1
                                );
                                if (result) return result;
                            }
                        }
                    } catch (e) {
                        // Skip inaccessible directories
                    }
                    return null;
                }
                
                document.getElementById('currentPlaylistTitle').innerHTML = 
                    '<i class="fas fa-spinner fa-spin"></i><span>Loading audio files from USB...</span>';
                
                for (const track of result.tracks) {
                    const blobURL = await findAudioFile(track.file_path);
                    if (blobURL) {
                        usbTracks.push({
                            ...track,
                            file_path: blobURL,
                            _usb_source: true,
                            _original_path: track.file_path
                        });
                    }
                }
                
                tracksData.length = 0;
                tracksData.push(...usbTracks);
                usbTracksData = usbTracks;
                
                currentPlaylistId = 'usb_all';
                document.getElementById('currentPlaylistTitle').innerHTML = 
                    '<i class="fas fa-usb"></i><span>USB Drive: ' + escapeHtml(usbRootHandle.name) + 
                    ' <span class="text-xs text-gray-500">(' + usbTracks.length + ' tracks)</span></span>';

                document.querySelectorAll('.playlist-item').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.getElementById('usb_drive_browser').classList.add('active');

                renderTracks(usbTracks);
                
                if (window.dualPlayer) {
                    window.dualPlayer.showNotification(
                        `USB Drive: ${usbTracks.length} tracks, ${result.playlists.length} playlists loaded`,
                        'success',
                        4000
                    );
                }
                
                window.usbPlaylists = result.playlists;
                window.usbRootName = usbRootHandle.name;
                
                renderUSBPlaylists(result.playlists, usbRootHandle.name);
                
            } catch (error) {
                console.error('[USB Drive] Database parse error:', error);
                alert('Gagal parse database Rekordbox:\n\n' + error.message);
            }
        }

        window.showUSBPlaylist = function(playlistId) {
            if (!window.usbPlaylists || !tracksData) {
                return;
            }
            
            const playlist = window.usbPlaylists.find(p => p.id == playlistId);
            if (!playlist) return;
            
            const trackIds = playlist.entries || [];
            const playlistTracks = tracksData.filter(t => trackIds.includes(t.id));
            
            const validCount = playlistTracks.length;
            const totalCount = trackIds.length;
            
            const countDisplay = validCount < totalCount 
                ? `${validCount} <span class="text-orange-400 text-xs" title="${totalCount - validCount} track tidak ditemukan">⚠</span>` 
                : validCount;
            
            currentPlaylistId = 'usb_playlist_' + playlistId;
            document.getElementById('currentPlaylistTitle').innerHTML = 
                '<i class="fas fa-list"></i><span>' + escapeHtml(playlist.name) + 
                ' <span class="text-xs text-gray-500">(' + countDisplay + ')</span></span>';
            
            document.querySelectorAll('.playlist-item').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.getElementById('usb_playlist_' + playlistId);
            if (activeBtn) activeBtn.classList.add('active');
            
            renderTracks(playlistTracks);
        }

        function renderUSBPlaylists(playlists, driveName) {
            const usbButton = document.getElementById('usb_drive_browser');
            if (!usbButton) return;
            
            let existingContainer = document.getElementById('usb_playlists_container');
            if (existingContainer) {
                existingContainer.remove();
            }
            
            const container = document.createElement('div');
            container.id = 'usb_playlists_container';
            container.className = 'mt-2 ml-2 border-l-2 border-purple-700/50 pl-2';
            
            function buildPlaylistTree(playlists) {
                const tree = [];
                const byId = {};
                
                playlists.forEach(playlist => {
                    byId[playlist.id] = { ...playlist, children: [] };
                });
                
                playlists.forEach(playlist => {
                    if (playlist.parent_id == 0) {
                        tree.push(byId[playlist.id]);
                    } else if (byId[playlist.parent_id]) {
                        byId[playlist.parent_id].children.push(byId[playlist.id]);
                    }
                });
                
                return tree;
            }
            
            function renderPlaylistNode(playlist, level = 0) {
                const indent = '&nbsp;'.repeat(level * 3);
                
                if (playlist.is_folder) {
                    const folderDiv = document.createElement('div');
                    folderDiv.className = 'folder-item';
                    
                    const folderBtn = document.createElement('button');
                    folderBtn.className = 'w-full text-left px-2 py-1.5 text-sm text-gray-400 hover:text-purple-400 hover:bg-gray-800 font-semibold transition-all rounded';
                    folderBtn.innerHTML = indent + '<i class="fas fa-folder mr-1.5"></i> ' + escapeHtml(playlist.name);
                    folderDiv.appendChild(folderBtn);
                    
                    if (playlist.children && playlist.children.length > 0) {
                        const childrenDiv = document.createElement('div');
                        childrenDiv.className = 'ml-2';
                        playlist.children.forEach(child => {
                            childrenDiv.appendChild(renderPlaylistNode(child, level + 1));
                        });
                        folderDiv.appendChild(childrenDiv);
                    }
                    
                    return folderDiv;
                } else {
                    const btn = document.createElement('button');
                    btn.id = 'usb_playlist_' + playlist.id;
                    btn.className = 'playlist-item w-full text-left px-2 py-1.5 rounded mb-0.5 text-sm';
                    btn.onclick = () => showUSBPlaylist(playlist.id);
                    
                    const flex = document.createElement('div');
                    flex.className = 'flex items-center justify-between';
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.className = 'truncate';
                    nameSpan.innerHTML = indent + '<i class="fas fa-music mr-1.5"></i> ' + escapeHtml(playlist.name);
                    
                    const countSpan = document.createElement('span');
                    countSpan.className = 'text-xs opacity-60 ml-2';
                    countSpan.textContent = playlist.track_count || 0;
                    
                    flex.appendChild(nameSpan);
                    flex.appendChild(countSpan);
                    btn.appendChild(flex);
                    
                    return btn;
                }
            }
            
            const tree = buildPlaylistTree(playlists);
            tree.forEach(playlist => {
                container.appendChild(renderPlaylistNode(playlist));
            });
            
            usbButton.parentElement.appendChild(container);
        }
    </script>
</body>
</html>
