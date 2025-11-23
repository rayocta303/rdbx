class AudioPlayer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.audio = null;
        this.currentTrack = null;
        this.isPlaying = false;
        this.playheadUpdateInterval = null;
        this.onTimeUpdateCallback = null;
        this.onDurationChangeCallback = null;
        this.onPlayStateChangeCallback = null;
        
        this.createUI();
    }
    
    createUI() {
        this.container.innerHTML = `
            <div class="bg-gray-100 rounded-lg p-4">
                <div class="flex items-center gap-4 mb-3">
                    <button id="playPauseBtn" class="w-12 h-12 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold disabled:opacity-50" disabled>
                        ▶
                    </button>
                    <div class="flex-1">
                        <div id="trackTitle" class="font-semibold text-gray-800 text-sm">No track loaded</div>
                        <div id="timeDisplay" class="text-xs text-gray-600">0:00 / 0:00</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-600">🔊</span>
                        <input type="range" id="volumeSlider" min="0" max="100" value="75" class="w-24">
                        <span id="volumeDisplay" class="text-xs text-gray-600 w-8">75%</span>
                    </div>
                </div>
                <div class="relative h-2 bg-gray-300 rounded-full cursor-pointer" id="progressBar">
                    <div id="progressFill" class="absolute h-full bg-blue-500 rounded-full" style="width: 0%"></div>
                    <div id="playheadIndicator" class="absolute w-1 h-4 bg-red-500 -top-1 rounded" style="left: 0%"></div>
                </div>
            </div>
        `;
        
        this.playPauseBtn = document.getElementById('playPauseBtn');
        this.trackTitle = document.getElementById('trackTitle');
        this.timeDisplay = document.getElementById('timeDisplay');
        this.volumeSlider = document.getElementById('volumeSlider');
        this.volumeDisplay = document.getElementById('volumeDisplay');
        this.progressBar = document.getElementById('progressBar');
        this.progressFill = document.getElementById('progressFill');
        this.playheadIndicator = document.getElementById('playheadIndicator');
        
        this.attachEventListeners();
    }
    
    attachEventListeners() {
        this.playPauseBtn.addEventListener('click', () => this.togglePlayPause());
        
        this.volumeSlider.addEventListener('input', (e) => {
            const volume = e.target.value / 100;
            if (this.audio) this.audio.volume = volume;
            this.volumeDisplay.textContent = e.target.value + '%';
        });
        
        this.progressBar.addEventListener('click', (e) => {
            if (!this.audio || !this.audio.duration) return;
            const rect = this.progressBar.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.audio.currentTime = percent * this.audio.duration;
        });
    }
    
    loadTrack(track) {
        this.currentTrack = track;
        
        if (this.audio) {
            this.audio.pause();
            this.audio = null;
        }
        
        this.audio = new Audio();
        
        // Use audio.php endpoint to stream audio files, or blob URL for USB tracks
        let audioPath;
        if (track._usb_source) {
            audioPath = track.file_path; // Blob URL, use directly
        } else {
            audioPath = 'audio.php?path=' + encodeURIComponent(track.file_path);
        }
        this.audio.src = audioPath;
        this.audio.volume = this.volumeSlider.value / 100;
        
        this.audio.addEventListener('loadedmetadata', () => {
            this.playPauseBtn.disabled = false;
            this.updateTimeDisplay();
            if (this.onDurationChangeCallback) {
                this.onDurationChangeCallback(this.audio.duration);
            }
        });
        
        this.audio.addEventListener('error', (e) => {
            console.error('Audio load error:', e);
            this.trackTitle.textContent = 'Error loading audio file';
            this.playPauseBtn.disabled = true;
        });
        
        this.audio.addEventListener('timeupdate', () => {
            this.updateTimeDisplay();
            this.updateProgress();
            if (this.onTimeUpdateCallback) {
                this.onTimeUpdateCallback(this.audio.currentTime, this.audio.duration);
            }
        });
        
        this.audio.addEventListener('ended', () => {
            this.isPlaying = false;
            this.playPauseBtn.textContent = '▶';
            if (this.onPlayStateChangeCallback) {
                this.onPlayStateChangeCallback(false);
            }
        });
        
        this.trackTitle.textContent = track.title;
        this.timeDisplay.textContent = '0:00 / 0:00';
        this.isPlaying = false;
        this.playPauseBtn.textContent = '▶';
    }
    
    togglePlayPause() {
        if (!this.audio) return;
        
        if (this.isPlaying) {
            this.audio.pause();
            this.isPlaying = false;
            this.playPauseBtn.textContent = '▶';
        } else {
            this.audio.play();
            this.isPlaying = true;
            this.playPauseBtn.textContent = '⏸';
        }
        
        if (this.onPlayStateChangeCallback) {
            this.onPlayStateChangeCallback(this.isPlaying);
        }
    }
    
    updateTimeDisplay() {
        if (!this.audio) return;
        
        const current = this.formatTime(this.audio.currentTime || 0);
        const total = this.formatTime(this.audio.duration || 0);
        this.timeDisplay.textContent = `${current} / ${total}`;
    }
    
    updateProgress() {
        if (!this.audio || !this.audio.duration) return;
        
        const percent = (this.audio.currentTime / this.audio.duration) * 100;
        this.progressFill.style.width = percent + '%';
        this.playheadIndicator.style.left = percent + '%';
    }
    
    formatTime(seconds) {
        if (!isFinite(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    seekTo(time) {
        if (this.audio) {
            this.audio.currentTime = time;
        }
    }
    
    getCurrentTime() {
        return this.audio ? this.audio.currentTime : 0;
    }
    
    getDuration() {
        return this.audio ? this.audio.duration : 0;
    }
    
    onTimeUpdate(callback) {
        this.onTimeUpdateCallback = callback;
    }
    
    onDurationChange(callback) {
        this.onDurationChangeCallback = callback;
    }
    
    onPlayStateChange(callback) {
        this.onPlayStateChangeCallback = callback;
    }
}
