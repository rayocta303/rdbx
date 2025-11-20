// Rekordbox Export Reader - Web App

let currentData = {
    tracks: [],
    playlists: [],
    corruptPlaylists: [],
    stats: {}
};

// DOM Elements
const parseBtn = document.getElementById('parseBtn');
const demoBtn = document.getElementById('demoBtn');
const exportBtn = document.getElementById('exportBtn');
const exportPath = document.getElementById('exportPath');
const statusMessage = document.getElementById('statusMessage');
const loadingOverlay = document.getElementById('loadingOverlay');
const statsGrid = document.getElementById('statsGrid');
const tabsContainer = document.getElementById('tabsContainer');

// Event Listeners
parseBtn.addEventListener('click', () => parseExport());
demoBtn.addEventListener('click', () => parseDemoExport());
exportBtn.addEventListener('click', () => exportJSON());

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

// Parse Export
async function parseExport() {
    const path = exportPath.value.trim();
    
    showLoading(true);
    hideStatus();
    
    try {
        const response = await fetch('/api/parse', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ export_path: path })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Parsing failed');
        }
        
        // Update stats
        currentData.stats = data.stats;
        currentData.corruptPlaylists = data.corrupt_playlists || [];
        
        updateStats(data.stats);
        showStatus('success', `✅ Parsing berhasil! ${data.stats.total_tracks} tracks, ${data.stats.total_playlists} playlists`);
        
        // Load data
        await loadTracks();
        await loadPlaylists();
        displayCorruptPlaylists(currentData.corruptPlaylists);
        
        // Show results
        statsGrid.style.display = 'grid';
        tabsContainer.style.display = 'block';
        
    } catch (error) {
        showStatus('error', `❌ Error: ${error.message}`);
        console.error('Parsing error:', error);
    } finally {
        showLoading(false);
    }
}

// Parse Demo Export
async function parseDemoExport() {
    showLoading(true);
    hideStatus();
    
    try {
        const response = await fetch('/api/demo/parse');
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Demo parsing failed');
        }
        
        currentData.stats = data.stats;
        
        updateStats(data.stats);
        showStatus('success', `✅ Demo parsing berhasil! ${data.message}`);
        
        await loadTracks();
        await loadPlaylists();
        displayCorruptPlaylists([]);
        
        statsGrid.style.display = 'grid';
        tabsContainer.style.display = 'block';
        
    } catch (error) {
        showStatus('error', `❌ Error: ${error.message}`);
        console.error('Demo parsing error:', error);
    } finally {
        showLoading(false);
    }
}

// Load Tracks
async function loadTracks() {
    try {
        const response = await fetch('/api/tracks');
        const data = await response.json();
        
        currentData.tracks = data.tracks;
        displayTracks(data.tracks);
        
    } catch (error) {
        console.error('Error loading tracks:', error);
    }
}

// Load Playlists
async function loadPlaylists() {
    try {
        const response = await fetch('/api/playlists');
        const data = await response.json();
        
        currentData.playlists = data.playlists;
        displayPlaylists(data.playlists);
        
    } catch (error) {
        console.error('Error loading playlists:', error);
    }
}

// Display Tracks
function displayTracks(tracks) {
    const tracksList = document.getElementById('tracksList');
    
    if (!tracks || tracks.length === 0) {
        tracksList.innerHTML = '<p class="empty-state">No tracks found in export</p>';
        return;
    }
    
    tracksList.innerHTML = tracks.map(track => `
        <div class="data-item">
            <div class="item-title">${escapeHtml(track.title || 'Unknown Track')}</div>
            <div class="item-meta">
                <span>🎤 ${escapeHtml(track.artist || 'Unknown Artist')}</span>
                <span>💿 ${escapeHtml(track.album || 'Unknown Album')}</span>
                ${track.bpm ? `<span>⚡ ${track.bpm} BPM</span>` : ''}
                ${track.key ? `<span>🎹 ${escapeHtml(track.key)}</span>` : ''}
                ${track.duration ? `<span>⏱️ ${formatDuration(track.duration)}</span>` : ''}
            </div>
        </div>
    `).join('');
}

// Display Playlists
function displayPlaylists(playlists) {
    const playlistsList = document.getElementById('playlistsList');
    
    if (!playlists || playlists.length === 0) {
        playlistsList.innerHTML = '<p class="empty-state">No playlists found in export</p>';
        return;
    }
    
    playlistsList.innerHTML = playlists.map(playlist => `
        <div class="data-item">
            <div class="item-title">${playlist.is_folder ? '📁' : '🎵'} ${escapeHtml(playlist.name)}</div>
            <div class="item-meta">
                <span>ID: ${playlist.id}</span>
                <span>Tracks: ${playlist.track_count || 0}</span>
                ${playlist.is_folder ? '<span>Type: Folder</span>' : '<span>Type: Playlist</span>'}
            </div>
        </div>
    `).join('');
}

// Display Corrupt Playlists
function displayCorruptPlaylists(corruptPlaylists) {
    const corruptList = document.getElementById('corruptList');
    
    if (!corruptPlaylists || corruptPlaylists.length === 0) {
        corruptList.innerHTML = '<p class="empty-state">✅ No corrupt playlists detected - all playlists parsed successfully!</p>';
        return;
    }
    
    corruptList.innerHTML = corruptPlaylists.map(item => `
        <div class="data-item corrupt-item">
            <div class="corrupt-reason">⚠️ ${escapeHtml(item.reason)}</div>
            <div class="item-title">${escapeHtml(item.playlist_name)}</div>
            <div class="item-meta">
                <span>Detected: ${new Date(item.timestamp).toLocaleString()}</span>
                ${item.details ? `<span>Details: ${JSON.stringify(item.details)}</span>` : ''}
            </div>
        </div>
    `).join('');
}

// Update Statistics
function updateStats(stats) {
    document.getElementById('statTracks').textContent = stats.total_tracks || 0;
    document.getElementById('statPlaylists').textContent = stats.total_playlists || 0;
    document.getElementById('statValid').textContent = stats.valid_playlists || 0;
    document.getElementById('statCorrupt').textContent = stats.corrupt_playlists || 0;
}

// Export JSON
async function exportJSON() {
    try {
        window.location.href = '/api/export/json';
        showStatus('success', '💾 JSON file downloaded!');
    } catch (error) {
        showStatus('error', `❌ Export error: ${error.message}`);
    }
}

// Tab Switching
function switchTab(tabName) {
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    
    // Update panes
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    
    const targetPane = {
        'tracks': 'tracksTab',
        'playlists': 'playlistsTab',
        'corrupt': 'corruptTab'
    }[tabName];
    
    document.getElementById(targetPane).classList.add('active');
}

// Helper Functions
function showLoading(show) {
    loadingOverlay.style.display = show ? 'flex' : 'none';
    parseBtn.disabled = show;
    demoBtn.disabled = show;
}

function showStatus(type, message) {
    statusMessage.className = `status-message ${type}`;
    statusMessage.textContent = message;
    statusMessage.style.display = 'block';
}

function hideStatus() {
    statusMessage.style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDuration(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    console.log('Rekordbox Export Reader initialized');
});
