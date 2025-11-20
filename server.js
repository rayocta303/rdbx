const express = require('express');
const path = require('path');
const fs = require('fs').promises;

const app = express();
const PORT = 5000;

app.use(express.static('public'));
app.use('/music', express.static('Rekordbox-USB/Contents'));
app.use('/artwork', express.static('Rekordbox-USB/PIONEER/Artwork'));

app.get('/api/library', async (req, res) => {
  try {
    const musicData = {
      artists: [],
      tracks: []
    };

    const contentsPath = path.join(__dirname, 'Rekordbox-USB', 'Contents');
    const artists = await fs.readdir(contentsPath);

    for (const artist of artists) {
      const artistPath = path.join(contentsPath, artist);
      const stats = await fs.stat(artistPath);
      
      if (stats.isDirectory()) {
        const albums = await fs.readdir(artistPath);
        
        for (const album of albums) {
          const albumPath = path.join(artistPath, album);
          const albumStats = await fs.stat(albumPath);
          
          if (albumStats.isDirectory()) {
            const tracks = await fs.readdir(albumPath);
            
            for (const track of tracks) {
              if (track.endsWith('.mp3') || track.endsWith('.wav') || track.endsWith('.flac')) {
                musicData.tracks.push({
                  artist: artist,
                  album: album,
                  title: track.replace(/\.(mp3|wav|flac)$/i, ''),
                  file: track,
                  path: `/music/${artist}/${album}/${track}`
                });
              }
            }
          }
        }
        
        if (!musicData.artists.includes(artist)) {
          musicData.artists.push(artist);
        }
      }
    }

    res.json(musicData);
  } catch (error) {
    console.error('Error reading library:', error);
    res.status(500).json({ error: 'Failed to read music library' });
  }
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Rekordbox Viewer running on http://0.0.0.0:${PORT}`);
});
