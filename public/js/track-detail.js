class TrackDetailPanel {
    constructor() {
        this.container = document.getElementById("trackDetailPanel");
        this.currentTrack = null;
        this.audioPlayer = null;
        this.waveformRenderer = null;
        this.cueManager = null;
        this.loadRequestCounter = 0; // Track in-flight fetch requests

        this.init();
    }

    init() {
        this.audioPlayer = new AudioPlayer("audioPlayerContainer");
        this.waveformRenderer = new WaveformRenderer(
            "waveformOverview",
            "waveformDetailed",
        );
        this.cueManager = new CueManager(
            "waveformOverview",
            "waveformDetailed",
            "cueListContainer",
        );

        this.audioPlayer.onTimeUpdate((currentTime, duration) => {
            this.waveformRenderer.updatePlayhead(currentTime);
            this.cueManager.renderCuesOnWaveform(this.waveformRenderer);
        });

        this.audioPlayer.onDurationChange((duration) => {
            if (this.currentTrack && this.currentTrack.waveform) {
                this.waveformRenderer.loadWaveform(
                    this.currentTrack.waveform,
                    duration,
                );
                this.cueManager.loadCues(
                    this.currentTrack.cue_points,
                    duration,
                );
                this.cueManager.renderCuesOnWaveform(this.waveformRenderer);
            }
        });

        this.waveformRenderer.onClick((time) => {
            this.audioPlayer.seekTo(time);
        });
    }

    show() {
        this.container.classList.remove("hidden");
    }

    hide() {
        this.container.classList.add("hidden");
        if (this.audioPlayer) {
            this.audioPlayer.audio && this.audioPlayer.audio.pause();
        }
    }

    async loadTrack(track) {
        this.currentTrack = track;
        this.show();

        document.getElementById("detailTrackTitle").textContent = track.title;
        document.getElementById("detailTrackArtist").textContent = track.artist;
        document.getElementById("detailTrackBPM").textContent =
            track.bpm.toFixed(2);
        document.getElementById("detailTrackKey").textContent = track.key;
        document.getElementById("detailTrackGenre").textContent =
            track.genre || "-";
        document.getElementById("detailTrackDuration").textContent =
            this.formatDuration(track.duration);

        const ratingElement = document.getElementById("detailTrackRating");
        if (track.rating && track.rating > 0) {
            ratingElement.innerHTML =
                '<i class="fas fa-star text-yellow-400"></i>'.repeat(
                    track.rating,
                );
        } else {
            ratingElement.textContent = "-";
        }

        const keyElement = document.getElementById("detailTrackKey");
        keyElement.className = "metadata-value text-sm";
        if (track.key) {
            if (track.key.endsWith("A") || track.key.endsWith("m")) {
                keyElement.classList.add("key-minor");
            } else {
                keyElement.classList.add("key-major");
            }
        }

        this.audioPlayer.loadTrack(track);

        // Generate unique load request ID to prevent stale fetch overwrites
        const loadRequestId = ++this.loadRequestCounter;

        // Clear previous waveform immediately to show loading state
        this.renderWaveformAndCues(track);

        // Lazy-load waveform data if not already present
        if (!track.waveform && track.id) {
            console.log(`[TrackDetail] Fetching analysis data for track ${track.id}...`);
            try {
                const response = await fetch(`/api/track-analysis.php?id=${track.id}`);
                
                // Verify this response is still relevant (user didn't switch tracks)
                if (loadRequestId !== this.loadRequestCounter) {
                    console.log(`[TrackDetail] Ignoring stale fetch for track ${track.id}`);
                    return;
                }
                
                if (response.ok) {
                    const analysisData = await response.json();
                    
                    // Double-check after JSON parsing (user might have switched during parse)
                    if (loadRequestId !== this.loadRequestCounter) {
                        console.log(`[TrackDetail] Ignoring stale fetch for track ${track.id} (post-parse)`);
                        return;
                    }
                    
                    // Verify current track still matches fetched track
                    if (!this.currentTrack || this.currentTrack.id !== track.id) {
                        console.log(`[TrackDetail] Current track changed, ignoring fetch for track ${track.id}`);
                        return;
                    }
                    
                    // Safe to update - still the current track
                    this.currentTrack.waveform = analysisData.waveform;
                    this.currentTrack.beat_grid = analysisData.beat_grid;
                    // cue_points already included in initial data, but update if API has newer
                    if (analysisData.cue_points && analysisData.cue_points.length > 0) {
                        this.currentTrack.cue_points = analysisData.cue_points;
                    }
                    console.log(`[TrackDetail] Analysis data loaded successfully for track ${track.id}`);
                    
                    // Re-render using this.currentTrack to avoid stale reference
                    this.renderWaveformAndCues(this.currentTrack);
                } else {
                    console.warn(`[TrackDetail] Failed to load analysis data:`, response.status);
                }
            } catch (error) {
                console.error(`[TrackDetail] Error loading analysis data:`, error);
            }
        }
    }

    renderWaveformAndCues(track) {
        if (
            !track.waveform ||
            (!track.waveform.color_data && !track.waveform.preview_data)
        ) {
            this.waveformRenderer.clear();
            this.cueManager.loadCues(track.cue_points || [], track.duration || 0);
        } else {
            // Load waveform and cues
            if (track.duration && track.duration > 0) {
                this.waveformRenderer.loadWaveform(
                    track.waveform,
                    track.duration,
                );
                if (track.cue_points && track.cue_points.length > 0) {
                    this.cueManager.loadCues(track.cue_points, track.duration);
                    this.cueManager.renderCuesOnWaveform(this.waveformRenderer);
                }
            }
        }
    }

    jumpToCue(timeInSeconds) {
        this.audioPlayer.seekTo(timeInSeconds);
    }

    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, "0")}`;
    }
}
