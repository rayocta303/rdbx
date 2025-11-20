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
        
        const ratingElement = document.getElementById('detailTrackRating');
        if (track.rating && track.rating > 0) {
            ratingElement.innerHTML = '<i class="fas fa-star text-yellow-400"></i>'.repeat(track.rating);
        } else {
            ratingElement.textContent = '-';
        }
        
        const keyElement = document.getElementById('detailTrackKey');
        keyElement.className = 'metadata-value text-xl';
        if (track.key) {
            if (track.key.endsWith('A') || track.key.endsWith('m')) {
                keyElement.classList.add('key-minor');
            } else {
                keyElement.classList.add('key-major');
            }
        }
        
        console.log('TrackDetailPanel.loadTrack:', {
            title: track.title,
            duration: track.duration,
            hasWaveform: !!track.waveform,
            hasColorData: track.waveform?.color_data?.length || 0,
            hasPreviewData: track.waveform?.preview_data?.length || 0,
            hasCuePoints: track.cue_points?.length || 0
        });
        
        this.audioPlayer.loadTrack(track);
        
        if (!track.waveform || (!track.waveform.color_data && !track.waveform.preview_data)) {
            console.log('No waveform data, clearing...');
            this.waveformRenderer.clear();
            this.cueManager.loadCues([], 0);
        } else {
            console.log('Waveform data found, loading...');
            // Load waveform and cues immediately if data exists
            if (track.duration && track.duration > 0) {
                this.waveformRenderer.loadWaveform(track.waveform, track.duration);
                if (track.cue_points && track.cue_points.length > 0) {
                    this.cueManager.loadCues(track.cue_points, track.duration);
                    this.cueManager.renderCuesOnWaveform(this.waveformRenderer);
                }
            } else {
                console.log('No duration, skipping waveform load');
            }
        }
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
