<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekordbox Export Reader - PHP Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
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
                <div class="text-right">
                    <button 
                        id="loadButton" 
                        onclick="loadRekordboxData()"
                        class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg shadow transition duration-200">
                        📀 Load Database
                    </button>
                </div>
            </div>

            <div id="loading" class="hidden text-center py-12">
                <div class="loader mx-auto mb-4"></div>
                <p class="text-gray-600 text-lg">Membaca dan parsing Rekordbox database...</p>
                <p class="text-gray-500 text-sm mt-2">Mohon tunggu, proses ini mungkin memakan waktu beberapa detik</p>
            </div>

            <div id="error" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h3 class="text-red-800 font-semibold mb-2">❌ Error</h3>
                <p id="errorMessage" class="text-red-700"></p>
            </div>

            <div id="stats" class="hidden grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold" id="totalTracks">0</div>
                    <div class="text-blue-100 text-sm">Total Tracks</div>
                </div>
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold" id="totalPlaylists">0</div>
                    <div class="text-green-100 text-sm">Total Playlists</div>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold" id="validPlaylists">0</div>
                    <div class="text-purple-100 text-sm">Valid Playlists</div>
                </div>
                <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold" id="corruptPlaylists">0</div>
                    <div class="text-yellow-100 text-sm">Corrupt Playlists</div>
                </div>
                <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg p-4 text-white shadow">
                    <div class="text-3xl font-bold" id="processingTime">0s</div>
                    <div class="text-indigo-100 text-sm">Processing Time</div>
                </div>
            </div>
        </div>

        <div id="content" class="hidden">
            <div class="bg-white rounded-lg shadow-lg mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button onclick="showTab('tracks')" id="tracksTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                            🎵 Tracks
                        </button>
                        <button onclick="showTab('playlists')" id="playlistsTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            📋 Playlists
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
                            <tbody id="tracksTable" class="bg-white divide-y divide-gray-200"></tbody>
                        </table>
                    </div>
                </div>

                <div id="playlistsContent" class="tab-content p-6 hidden">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Playlists</h2>
                    <div id="playlistsList" class="space-y-3"></div>
                </div>

                <div id="metadataContent" class="tab-content p-6 hidden">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Database Metadata</h2>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <pre id="metadataDisplay" class="text-sm text-gray-700 overflow-x-auto"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allTracks = [];
        let allPlaylists = [];
        let metadata = {};

        async function loadRekordboxData() {
            const loadButton = document.getElementById('loadButton');
            const loading = document.getElementById('loading');
            const error = document.getElementById('error');
            const stats = document.getElementById('stats');
            const content = document.getElementById('content');

            loadButton.disabled = true;
            loading.classList.remove('hidden');
            error.classList.add('hidden');
            stats.classList.add('hidden');
            content.classList.add('hidden');

            try {
                const response = await fetch('../api/read.php?path=./Rekordbox-USB');
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error);
                }

                allTracks = result.data.tracks;
                allPlaylists = result.data.playlists;
                metadata = result.data.metadata;

                document.getElementById('totalTracks').textContent = result.stats.total_tracks;
                document.getElementById('totalPlaylists').textContent = result.stats.total_playlists;
                document.getElementById('validPlaylists').textContent = result.stats.valid_playlists;
                document.getElementById('corruptPlaylists').textContent = result.stats.corrupt_playlists;
                document.getElementById('processingTime').textContent = result.stats.processing_time + 's';

                displayTracks(allTracks);
                displayPlaylists(allPlaylists);
                displayMetadata(metadata);

                stats.classList.remove('hidden');
                content.classList.remove('hidden');
                loading.classList.add('hidden');

            } catch (err) {
                document.getElementById('errorMessage').textContent = err.message;
                error.classList.remove('hidden');
                loading.classList.add('hidden');
            } finally {
                loadButton.disabled = false;
            }
        }

        function displayTracks(tracks) {
            const tbody = document.getElementById('tracksTable');
            tbody.innerHTML = '';

            tracks.forEach(track => {
                const row = document.createElement('tr');
                row.className = 'track-row';
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm text-gray-600">${track.id}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${escapeHtml(track.title)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(track.artist)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(track.album)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${track.bpm.toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(track.key)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${formatDuration(track.duration)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function displayPlaylists(playlists) {
            const container = document.getElementById('playlistsList');
            container.innerHTML = '';

            playlists.forEach(playlist => {
                const div = document.createElement('div');
                div.className = 'bg-gray-50 rounded-lg p-4 border border-gray-200';
                
                const icon = playlist.is_folder ? '📁' : '🎵';
                
                div.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">${icon}</span>
                            <div>
                                <h3 class="font-semibold text-gray-900">${escapeHtml(playlist.name)}</h3>
                                <p class="text-sm text-gray-500">${playlist.track_count} tracks</p>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500">ID: ${playlist.id}</span>
                    </div>
                `;
                container.appendChild(div);
            });
        }

        function displayMetadata(metadata) {
            document.getElementById('metadataDisplay').textContent = JSON.stringify(metadata, null, 2);
        }

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
            
            if (!searchTerm) {
                displayTracks(allTracks);
                return;
            }

            const filtered = allTracks.filter(track => 
                track.title.toLowerCase().includes(searchTerm) ||
                track.artist.toLowerCase().includes(searchTerm) ||
                track.album.toLowerCase().includes(searchTerm) ||
                track.key.toLowerCase().includes(searchTerm)
            );

            displayTracks(filtered);
        }

        function formatDuration(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
