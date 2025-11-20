let allTracks = [];
let currentTrack = null;

async function loadLibrary() {
    try {
        const response = await fetch('/api/library');
        const data = await response.json();
        
        allTracks = data.tracks;
        
        document.getElementById('trackCount').textContent = data.tracks.length;
        document.getElementById('artistCount').textContent = data.artists.length;
        
        displayTracks(allTracks);
    } catch (error) {
        console.error('Error loading library:', error);
        document.getElementById('trackList').innerHTML = '<div class="loading">Error loading music library</div>';
    }
}

function displayTracks(tracks) {
    const trackList = document.getElementById('trackList');
    
    if (tracks.length === 0) {
        trackList.innerHTML = '<div class="no-results">No tracks found</div>';
        return;
    }
    
    trackList.innerHTML = tracks.map(track => `
        <div class="track-item" onclick="playTrack('${escapeHtml(track.path)}', '${escapeHtml(track.title)}', '${escapeHtml(track.artist)}')">
            <div class="track-title">${escapeHtml(track.title)}</div>
            <div class="track-meta">
                <span class="track-artist">${escapeHtml(track.artist)}</span>
                ${track.album !== 'UnknownAlbum' ? ` • ${escapeHtml(track.album)}` : ''}
            </div>
        </div>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function playTrack(path, title, artist) {
    const player = document.getElementById('player');
    const audioPlayer = document.getElementById('audioPlayer');
    const playerTitle = document.getElementById('playerTitle');
    const playerArtist = document.getElementById('playerArtist');
    
    audioPlayer.src = path;
    playerTitle.textContent = title;
    playerArtist.textContent = artist;
    
    player.style.display = 'block';
    audioPlayer.play();
    
    currentTrack = { path, title, artist };
}

document.getElementById('searchInput').addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    
    if (!searchTerm) {
        displayTracks(allTracks);
        return;
    }
    
    const filtered = allTracks.filter(track => 
        track.title.toLowerCase().includes(searchTerm) ||
        track.artist.toLowerCase().includes(searchTerm) ||
        track.album.toLowerCase().includes(searchTerm)
    );
    
    displayTracks(filtered);
});

loadLibrary();
