class TrackDetailPanel {
    constructor() {
        this.container = document.getElementById('trackDetailPanel');
        this.currentTrack = null;
        this.audioPlayer = null;
        this.waveformRenderer = null;
        this.cueManager = null;
        
        this.init();
    }
    
    init() {
        this.audioPlayer = new AudioPlayer('audioPlayerContainer');
        this.waveformRenderer = new WaveformRenderer('waveformOverview', 'waveformDetailed');
        this.cueManager = new CueManager('waveformOverview', 'waveformDetailed', 'cueListContainer');
        
        this.audioPlayer.onTimeUpdate((currentTime, duration) => {
            this.waveformRenderer.updatePlayhead(currentTime);
            this.cueManager.renderCuesOnWaveform(this.waveformRenderer);
        });
        
        this.audioPlayer.onDurationChange((duration) => {
            if (this.currentTrack && this.currentTrack.waveform) {
                this.waveformRenderer.loadWaveform(this.currentTrack.waveform, duration);
                this.cueManager.loadCues(this.currentTrack.cue_points, duration);
                this.cueManager.renderCuesOnWaveform(this.waveformRenderer);
            }
        });
        
        this.waveformRenderer.onClick((time) => {
            this.audioPlayer.seekTo(time);
        });
    }
    
    show() {
        this.container.classList.remove('hidden');
    }
    
    hide() {
        this.container.classList.add('hidden');
        if (this.audioPlayer) {
            this.audioPlayer.audio && this.audioPlayer.audio.pause();
        }
    }
    
    loadTrack(track) {
        this.currentTrack = track;
        this.show();
        
        document.getElementById('detailTrackTitle').textContent = track.title;
        document.getElementById('detailTrackArtist').textContent = track.artist;
        document.getElementById('detailTrackBPM').textContent = track.bpm.toFixed(2);
        document.getElementById('detailTrackKey').textContent = track.key;
        document.getElementById('detailTrackGenre').textContent = track.genre || '-';
        document.getElementById('detailTrackDuration').textContent = this.formatDuration(track.duration);
        document.getElementById('detailTrackRating').textContent = '⭐'.repeat(track.rating || 0) || '-';
        
        const keyElement = document.getElementById('detailTrackKey');
        keyElement.className = '';
        if (track.key) {
            if (track.key.endsWith('A') || track.key.endsWith('m')) {
                keyElement.className = 'text-purple-600 font-bold';
            } else {
                keyElement.className = 'text-blue-600 font-bold';
            }
        }
        
        this.audioPlayer.loadTrack(track);
        
        this.waveformRenderer.clear();
        this.cueManager.loadCues([], 0);
    }
    
    jumpToCue(timeInSeconds) {
        this.audioPlayer.seekTo(timeInSeconds);
    }
    
    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
}
