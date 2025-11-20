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
