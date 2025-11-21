class DualPlayer {
    constructor() {
        this.sharedZoomLevel = 16;
        this.masterDeck = null;
        
        this.decks = {
            a: this.createDeck('a'),
            b: this.createDeck('b')
        };
        
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            console.log('[DualPlayer] Audio context initialized successfully');
        } catch (e) {
            console.error('[DualPlayer] Failed to initialize audio context:', e);
        }
        
        this.initializeDecks();
    }
    
    createDeck(deckId) {
        const audio = new Audio();
        audio.crossOrigin = 'anonymous';
        audio.preload = 'auto';
        
        return {
            id: deckId,
            track: null,
            audio: audio,
            source: null,
            gainNode: null,
            isPlaying: false,
            currentTime: 0,
            duration: 0,
            waveformOffset: 0,
            hotCues: {},
            waveformData: null,
            beatgridData: null,
            animationFrame: null,
            pitchValue: 0,
            masterTempo: false,
            originalBPM: 0,
            nudgeActive: false,
            nudgeAmount: 0,
            basePitchValue: 0,
            volume: 100,
            quantizeEnabled: false,
            isScratching: false,
            scratchDirection: 1,
            scratchSpeed: 0,
            scratchAnimationFrame: null
        };
    }
    
    initializeDecks() {
        ['a', 'b'].forEach(deckId => {
            const deck = this.decks[deckId];
            
            deck.gainNode = this.audioContext.createGain();
            deck.gainNode.connect(this.audioContext.destination);
            
            deck.audio.addEventListener('loadeddata', () => {
                console.log(`[Deck ${deckId.toUpperCase()}] Audio data loaded successfully`);
                if (!deck.source) {
                    try {
                        deck.source = this.audioContext.createMediaElementSource(deck.audio);
                        deck.source.connect(deck.gainNode);
                        console.log(`[Deck ${deckId.toUpperCase()}] Audio source connected to gain node`);
                    } catch (e) {
                        console.warn(`[Deck ${deckId.toUpperCase()}] Audio context already connected:`, e);
                    }
                }
            });
            
            deck.audio.addEventListener('timeupdate', () => this.updatePlayhead(deckId));
            deck.audio.addEventListener('loadedmetadata', () => this.onTrackLoaded(deckId));
            deck.audio.addEventListener('ended', () => this.onTrackEnded(deckId));
            
            deck.audio.addEventListener('error', (e) => {
                const error = deck.audio.error;
                console.error(`[Deck ${deckId.toUpperCase()}] Audio error:`, {
                    code: error?.code,
                    message: error?.message,
                    src: deck.audio.src,
                    readyState: deck.audio.readyState,
                    networkState: deck.audio.networkState
                });
                
                const errorMessages = {
                    1: 'MEDIA_ERR_ABORTED - The fetching process was aborted by the user',
                    2: 'MEDIA_ERR_NETWORK - A network error occurred while fetching',
                    3: 'MEDIA_ERR_DECODE - Error occurred while decoding the media',
                    4: 'MEDIA_ERR_SRC_NOT_SUPPORTED - The media format is not supported'
                };
                
                console.error(`[Deck ${deckId.toUpperCase()}] ${errorMessages[error?.code] || 'Unknown error'}`);
            });
            
            deck.audio.addEventListener('canplay', () => {
                console.log(`[Deck ${deckId.toUpperCase()}] Audio can start playing (canplay event)`);
            });
            
            deck.audio.addEventListener('canplaythrough', () => {
                console.log(`[Deck ${deckId.toUpperCase()}] Audio can play through without buffering (canplaythrough event)`);
            });
            
            this.setupWaveformInteraction(deckId);
        });
    }
    
    setupWaveformInteraction(deckId) {
        const canvas = document.getElementById(`waveformCanvas${deckId.toUpperCase()}`);
        const container = document.getElementById(`waveformContainer${deckId.toUpperCase()}`);
        
        if (!canvas || !container) return;
        
        let isDragging = false;
        let hasDragged = false;
        let startX = 0;
        let startCenter = 0;
        let lastX = 0;
        let lastCenter = 0;
        let lastTime = 0;
        let wasPlaying = false;
        
        container.style.cursor = 'grab';
        
        container.addEventListener('mousedown', (e) => {
            const deck = this.decks[deckId];
            const visibleDuration = deck.duration / this.sharedZoomLevel;
            isDragging = true;
            hasDragged = false;
            startX = e.clientX;
            lastX = e.clientX;
            startCenter = deck.waveformOffset + (visibleDuration / 2);
            lastCenter = startCenter;
            wasPlaying = deck.isPlaying;
            lastTime = Date.now();
            container.style.cursor = 'grabbing';
        });
        
        container.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            
            const deck = this.decks[deckId];
            if (!deck.duration || deck.duration <= 0) return;
            
            const deltaX = e.clientX - startX;
            
            if (Math.abs(deltaX) > 3) {
                hasDragged = true;
            }
            
            const pixelsPerSecond = canvas.width / (deck.duration / this.sharedZoomLevel);
            const deltaTime = deltaX / pixelsPerSecond;
            const visibleDuration = deck.duration / this.sharedZoomLevel;
            const minOffset = -visibleDuration / 2;
            
            const newCenter = startCenter - deltaTime;
            deck.waveformOffset = newCenter - (visibleDuration / 2);
            deck.waveformOffset = Math.max(minOffset, Math.min(deck.waveformOffset, deck.duration - visibleDuration));
            
            const centerTime = deck.waveformOffset + (visibleDuration / 2);
            const targetTime = Math.max(0, Math.min(centerTime, deck.duration));
            
            if (deck.audio && hasDragged) {
                deck.audio.currentTime = targetTime;
                
                if (wasPlaying) {
                    const now = Date.now();
                    const frameDeltaTime = (now - lastTime) / 1000;
                    
                    if (frameDeltaTime > 0) {
                        const centerDelta = newCenter - lastCenter;
                        const scratchSpeed = centerDelta / frameDeltaTime;
                        const direction = scratchSpeed >= 0 ? 1 : -1;
                        
                        deck.isScratching = true;
                        deck.scratchDirection = direction;
                        deck.scratchSpeed = Math.abs(scratchSpeed);
                        
                        if (direction < 0) {
                            if (!deck.audio.paused) {
                                deck.audio.pause();
                            }
                            this.startReverseScratch(deckId);
                        } else {
                            if (deck.scratchAnimationFrame) {
                                cancelAnimationFrame(deck.scratchAnimationFrame);
                                deck.scratchAnimationFrame = null;
                            }
                            if (deck.audio.paused) {
                                deck.audio.play();
                            }
                            const clampedRate = Math.max(0.25, Math.min(4, deck.scratchSpeed));
                            deck.audio.playbackRate = clampedRate;
                        }
                        
                        lastCenter = newCenter;
                        lastX = e.clientX;
                        lastTime = now;
                    }
                }
            }
            
            this.renderWaveform(deckId);
            this.renderCueMarkers(deckId);
            
            const deckLabel = deckId.toUpperCase();
            const timeDisplay = document.getElementById(`timeDisplay${deckLabel}`);
            if (timeDisplay) {
                timeDisplay.textContent = `${this.formatTime(targetTime)} / ${this.formatTime(deck.duration)}`;
            }
        });
        
        const endDrag = () => {
            if (!isDragging) return;
            
            const deck = this.decks[deckId];
            isDragging = false;
            container.style.cursor = 'grab';
            
            deck.isScratching = false;
            if (deck.scratchAnimationFrame) {
                cancelAnimationFrame(deck.scratchAnimationFrame);
                deck.scratchAnimationFrame = null;
            }
            
            if (deck.audio) {
                const normalRate = 1.0 + (deck.pitchValue / 100);
                deck.audio.playbackRate = Math.max(0.25, Math.min(4, normalRate));
                
                if (wasPlaying && deck.audio.paused) {
                    deck.audio.play();
                }
            }
        };
        
        container.addEventListener('mouseup', endDrag);
        container.addEventListener('mouseleave', endDrag);
        
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            
            const deck = this.decks[deckId];
            if (!deck.duration || deck.duration <= 0) return;
            
            const direction = e.deltaY < 0 ? 1 : -1;
            this.zoomBothDecks(direction);
        }, { passive: false });
    }
    
    startReverseScratch(deckId) {
        const deck = this.decks[deckId];
        
        if (deck.scratchAnimationFrame) {
            cancelAnimationFrame(deck.scratchAnimationFrame);
        }
        
        let lastFrameTime = Date.now();
        
        const reverseLoop = () => {
            if (!deck.isScratching || deck.scratchDirection >= 0) {
                deck.scratchAnimationFrame = null;
                return;
            }
            
            const now = Date.now();
            const deltaTime = (now - lastFrameTime) / 1000;
            lastFrameTime = now;
            
            const reverseSpeed = Math.max(0.25, Math.min(4, deck.scratchSpeed));
            const timeStep = reverseSpeed * deltaTime;
            
            const newTime = Math.max(0, deck.audio.currentTime - timeStep);
            deck.audio.currentTime = newTime;
            
            this.updatePlayhead(deckId);
            
            if (newTime > 0 && deck.isScratching && deck.scratchDirection < 0) {
                deck.scratchAnimationFrame = requestAnimationFrame(reverseLoop);
            } else {
                deck.scratchAnimationFrame = null;
            }
        };
        
        reverseLoop();
    }
    
    loadTrack(track, deckId) {
        const deck = this.decks[deckId];
        
        console.log(`[Deck ${deckId.toUpperCase()}] Loading track:`, {
            title: track.title,
            artist: track.artist,
            file_path: track.file_path,
            bpm: track.bpm,
            key: track.key,
            duration: track.duration
        });
        
        if (deck.isPlaying) {
            this.togglePlay(deckId);
        }
        
        deck.track = track;
        const audioSrc = `/audio.php?path=${encodeURIComponent(track.file_path)}`;
        console.log(`[Deck ${deckId.toUpperCase()}] Audio source URL:`, audioSrc);
        
        deck.audio.src = audioSrc;
        deck.audio.load();
        
        if (track.waveform) {
            deck.waveformData = track.waveform.color_data || track.waveform.preview_data || null;
            console.log(`[Deck ${deckId.toUpperCase()}] Waveform data available:`, !!deck.waveformData);
        } else {
            deck.waveformData = null;
            console.log(`[Deck ${deckId.toUpperCase()}] No waveform data available`);
        }
        
        deck.beatgridData = track.beat_grid;
        deck.waveformOffset = 0;
        deck.pitchValue = 0;
        deck.originalBPM = track.bpm || 0;
        
        const deckLabel = deckId.toUpperCase();
        const pitchSlider = document.getElementById(`pitchSlider${deckLabel}`);
        if (pitchSlider) {
            pitchSlider.value = 0;
        }
        const pitchValue = document.getElementById(`pitchValue${deckLabel}`);
        if (pitchValue) {
            pitchValue.textContent = '0.0%';
        }
        
        this.loadHotCues(track, deckId);
        this.updateTrackInfo(deckId);
        
        const trackInfoEl = document.getElementById(`trackInfo${deckLabel}`);
        if (trackInfoEl) {
            trackInfoEl.innerHTML = `
                <div class="track-title-compact">${this.escapeHtml(track.title)}</div>
                <div class="track-meta-compact">
                    <span class="bpm-display">${track.bpm.toFixed(2)} BPM</span>
                    <span class="key-display">${this.escapeHtml(track.key)}</span>
                </div>
            `;
        }
    }
    
    onTrackLoaded(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        deck.duration = deck.audio.duration;
        
        console.log(`[Deck ${deckLabel}] Track metadata loaded - Duration: ${deck.duration.toFixed(2)}s`);
        
        if (deck.duration > 0) {
            const visibleDuration = deck.duration / this.sharedZoomLevel;
            deck.waveformOffset = -visibleDuration / 2;
            
            const sharedZoomEl = document.getElementById('sharedZoomLevel');
            if (sharedZoomEl) {
                sharedZoomEl.textContent = `${this.sharedZoomLevel}x`;
            }
            
            console.log(`[Deck ${deckLabel}] Rendering waveform with zoom level: ${this.sharedZoomLevel}x`);
            this.renderWaveform(deckId);
            this.renderCueMarkers(deckId);
        } else {
            console.warn(`[Deck ${deckLabel}] Invalid duration: ${deck.duration}`);
        }
        
        this.updateTrackInfo(deckId);
    }
    
    loadHotCues(track, deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        deck.hotCues = {};
        
        for (let i = 1; i <= 8; i++) {
            const cueTimeEl = document.getElementById(`cueTime${deckLabel}${i}`);
            if (cueTimeEl) {
                cueTimeEl.textContent = '--:--';
            }
            
            const cuePad = document.querySelector(`.hot-cue-pad[data-deck="${deckId}"][data-cue="${i}"]`);
            if (cuePad) {
                cuePad.classList.remove('active');
            }
        }
        
        if (track.cue_points && track.cue_points.length > 0) {
            track.cue_points.forEach((cue) => {
                if (cue.type === 'cue' && cue.hot_cue !== null && cue.hot_cue >= 0 && cue.hot_cue < 8) {
                    const padNumber = cue.hot_cue + 1;
                    const timeInSeconds = cue.time / 1000;
                    
                    deck.hotCues[padNumber] = {
                        time: timeInSeconds,
                        label: cue.comment || `Cue ${padNumber}`
                    };
                    
                    const cueTimeEl = document.getElementById(`cueTime${deckLabel}${padNumber}`);
                    if (cueTimeEl) {
                        cueTimeEl.textContent = this.formatTime(timeInSeconds);
                    }
                    
                    const cuePad = document.querySelector(`.hot-cue-pad[data-deck="${deckId}"][data-cue="${padNumber}"]`);
                    if (cuePad) {
                        cuePad.classList.add('active');
                    }
                }
            });
        }
        
        this.renderCueMarkers(deckId);
    }
    
    async togglePlay(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const playIcon = document.getElementById(`playIcon${deckLabel}`);
        
        if (!deck.track) {
            console.warn(`[Deck ${deckLabel}] Cannot play: No track loaded`);
            return;
        }
        
        if (deck.isPlaying) {
            console.log(`[Deck ${deckLabel}] Pausing playback at ${deck.audio.currentTime.toFixed(2)}s`);
            deck.audio.pause();
            deck.isPlaying = false;
            playIcon.className = 'fas fa-play';
            if (deck.animationFrame) {
                cancelAnimationFrame(deck.animationFrame);
            }
        } else {
            console.log(`[Deck ${deckLabel}] Starting playback from ${deck.audio.currentTime.toFixed(2)}s`);
            
            if (this.audioContext && this.audioContext.state === 'suspended') {
                console.log(`[Deck ${deckLabel}] Resuming AudioContext...`);
                try {
                    await this.audioContext.resume();
                    console.log(`[Deck ${deckLabel}] AudioContext resumed, state: ${this.audioContext.state}`);
                } catch (err) {
                    console.error(`[Deck ${deckLabel}] Failed to resume AudioContext:`, err);
                }
            }
            
            try {
                await deck.audio.play();
                console.log(`[Deck ${deckLabel}] Playback started successfully`);
                deck.isPlaying = true;
                playIcon.className = 'fas fa-pause';
                this.startPlayheadAnimation(deckId);
            } catch (error) {
                console.error(`[Deck ${deckLabel}] Playback failed:`, error);
                console.error(`[Deck ${deckLabel}] Error details:`, {
                    name: error.name,
                    message: error.message,
                    audioSrc: deck.audio.src,
                    readyState: deck.audio.readyState,
                    networkState: deck.audio.networkState,
                    audioContextState: this.audioContext?.state
                });
                deck.isPlaying = false;
                playIcon.className = 'fas fa-play';
                
                alert(`Failed to play audio: ${error.message}\nCheck browser console for details.`);
            }
        }
    }
    
    startPlayheadAnimation(deckId) {
        const deck = this.decks[deckId];
        
        const animate = () => {
            if (!deck.isPlaying) return;
            
            this.updatePlayhead(deckId);
            
            deck.animationFrame = requestAnimationFrame(animate);
        };
        
        animate();
    }
    
    updatePlayhead(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        if (!deck.duration || deck.duration <= 0) return;
        
        const currentTime = deck.audio.currentTime;
        const duration = deck.duration;
        
        const visibleDuration = duration / this.sharedZoomLevel;
        const minOffset = -visibleDuration / 2;
        deck.waveformOffset = Math.max(minOffset, Math.min(currentTime - (visibleDuration / 2), duration - visibleDuration));
        
        const container = document.getElementById(`waveformContainer${deckLabel}`);
        if (container) {
            const playhead = container.querySelector('.playhead');
            if (playhead) {
                playhead.style.left = '50%';
            }
        }
        
        const timeDisplay = document.getElementById(`timeDisplay${deckLabel}`);
        if (timeDisplay) {
            timeDisplay.textContent = `${this.formatTime(currentTime)} / ${this.formatTime(duration)}`;
        }
        
        this.renderWaveform(deckId);
        this.renderCueMarkers(deckId);
    }
    
    zoomBothDecks(direction) {
        const zoomLevels = [16, 32, 64, 128];
        let currentIndex = zoomLevels.indexOf(this.sharedZoomLevel);
        
        if (direction > 0 && currentIndex < zoomLevels.length - 1) {
            currentIndex++;
        } else if (direction < 0 && currentIndex > 0) {
            currentIndex--;
        }
        
        this.sharedZoomLevel = zoomLevels[currentIndex];
        
        this.applyZoomToDeck('a');
        this.applyZoomToDeck('b');
        
        const sharedZoomEl = document.getElementById('sharedZoomLevel');
        if (sharedZoomEl) {
            sharedZoomEl.textContent = `${this.sharedZoomLevel}x`;
        }
    }
    
    applyZoomToDeck(deckId) {
        const deck = this.decks[deckId];
        
        if (!deck.duration || deck.duration <= 0) return;
        
        const currentTime = deck.audio.currentTime;
        const visibleDuration = deck.duration / this.sharedZoomLevel;
        const minOffset = -visibleDuration / 2;
        
        deck.waveformOffset = currentTime - (visibleDuration / 2);
        deck.waveformOffset = Math.max(minOffset, Math.min(deck.waveformOffset, deck.duration - visibleDuration));
        
        const centerTime = deck.waveformOffset + (visibleDuration / 2);
        const targetTime = Math.max(0, Math.min(centerTime, deck.duration));
        
        if (deck.audio && Math.abs(deck.audio.currentTime - targetTime) > 0.1) {
            deck.audio.currentTime = targetTime;
        }
        
        this.renderWaveform(deckId);
        this.renderCueMarkers(deckId);
    }
    
    renderWaveform(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const canvas = document.getElementById(`waveformCanvas${deckLabel}`);
        
        if (!canvas || !deck.duration || deck.duration <= 0) return;
        
        const container = canvas.parentElement;
        canvas.width = container.clientWidth * 2;
        canvas.height = 120 * 2;
        canvas.style.width = container.clientWidth + 'px';
        canvas.style.height = '120px';
        
        const ctx = canvas.getContext('2d', { alpha: true });
        
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        
        ctx.fillStyle = '#0a0a0a';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        if (!deck.waveformData) {
            ctx.fillStyle = '#333';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No waveform data', canvas.width / 2, canvas.height / 2);
            return;
        }
        
        const viewStart = deck.waveformOffset;
        const viewDuration = deck.duration / this.sharedZoomLevel;
        const viewEnd = viewStart + viewDuration;
        
        const leadingBlankDuration = Math.max(0, -viewStart);
        const leadingBlankWidth = (leadingBlankDuration / viewDuration) * canvas.width;
        
        const actualStart = Math.max(0, viewStart);
        const startIndex = Math.floor((actualStart / deck.duration) * deck.waveformData.length);
        const endIndex = Math.ceil((viewEnd / deck.duration) * deck.waveformData.length);
        const visibleData = deck.waveformData.slice(startIndex, endIndex);
        
        if (visibleData.length === 0) return;
        
        const dataWidth = canvas.width - leadingBlankWidth;
        const step = dataWidth / visibleData.length;
        const height = canvas.height;
        const isColorWaveform = visibleData[0] && visibleData[0].r !== undefined;
        
        visibleData.forEach((sample, i) => {
            const x = leadingBlankWidth + (i * step);
            const normalizedHeight = sample.height / 255;
            const barHeight = normalizedHeight * height * 0.9;
            const y = (height - barHeight) / 2;
            
            const barWidth = Math.max(1.5, step * 1.1);
            
            if (isColorWaveform) {
                const brightness = Math.max(sample.r, sample.g, sample.b) / 255;
                
                const gradient = ctx.createLinearGradient(x, y, x, y + barHeight);
                const r = sample.r;
                const g = sample.g;
                const b = sample.b;
                
                gradient.addColorStop(0, `rgba(${r}, ${g}, ${b}, ${0.9 + brightness * 0.1})`);
                gradient.addColorStop(0.5, `rgba(${r}, ${g}, ${b}, ${0.95})`);
                gradient.addColorStop(1, `rgba(${r}, ${g}, ${b}, ${0.9 + brightness * 0.1})`);
                
                ctx.fillStyle = gradient;
                
                ctx.shadowBlur = 2;
                ctx.shadowColor = `rgba(${r}, ${g}, ${b}, 0.4)`;
            } else {
                const intensity = normalizedHeight;
                
                const gradient = ctx.createLinearGradient(x, y, x, y + barHeight);
                gradient.addColorStop(0, `rgba(0, 217, 255, ${0.8 + intensity * 0.2})`);
                gradient.addColorStop(0.5, `rgba(0, 255, 200, ${0.85 + intensity * 0.15})`);
                gradient.addColorStop(1, `rgba(0, 217, 255, ${0.8 + intensity * 0.2})`);
                
                ctx.fillStyle = gradient;
                
                ctx.shadowBlur = 2;
                ctx.shadowColor = `rgba(0, 217, 255, ${0.3 + intensity * 0.2})`;
            }
            
            ctx.fillRect(x - barWidth/4, y, barWidth, barHeight);
            ctx.shadowBlur = 0;
        });
        
        this.renderBeatgrid(deckId, ctx, canvas.width, canvas.height, viewStart, viewDuration);
    }
    
    renderBeatgrid(deckId, ctx, width, height, viewStart, viewDuration) {
        const deck = this.decks[deckId];
        
        if (!deck.track || !deck.track.bpm || deck.track.bpm <= 0) return;
        
        const beatInterval = 60 / deck.track.bpm;
        const viewEnd = viewStart + viewDuration;
        
        const firstBeat = Math.ceil(viewStart / beatInterval) * beatInterval;
        
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
        ctx.lineWidth = 4;
        
        for (let beatTime = firstBeat; beatTime < viewEnd; beatTime += beatInterval) {
            const relativePosition = (beatTime - viewStart) / viewDuration;
            const x = relativePosition * width;
            
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
        }
    }
    
    renderCueMarkers(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const container = document.getElementById(`cueMarkers${deckLabel}`);
        
        if (!container || !deck.duration || deck.duration <= 0) return;
        
        container.innerHTML = '';
        
        const viewStart = deck.waveformOffset;
        const viewDuration = deck.duration / this.sharedZoomLevel;
        const viewEnd = viewStart + viewDuration;
        
        Object.entries(deck.hotCues).forEach(([cueNum, cue]) => {
            if (cue.time >= viewStart && cue.time <= viewEnd) {
                const relativePosition = (cue.time - viewStart) / viewDuration;
                const marker = document.createElement('div');
                marker.className = 'cue-marker';
                marker.style.left = `${relativePosition * 100}%`;
                marker.title = `Cue ${cueNum}: ${this.formatTime(cue.time)}`;
                marker.innerHTML = `<div class="cue-marker-label">${cueNum}</div>`;
                container.appendChild(marker);
            }
        });
    }
    
    ejectDeck(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        if (deck.isPlaying) {
            this.togglePlay(deckId);
        }
        
        deck.track = null;
        deck.audio.src = '';
        deck.waveformData = null;
        deck.beatgridData = null;
        deck.hotCues = {};
        deck.waveformOffset = 0;
        
        const trackInfoEl = document.getElementById(`trackInfo${deckLabel}`);
        if (trackInfoEl) {
            trackInfoEl.innerHTML = `
                <div class="track-title-compact">No Track Loaded</div>
                <div class="track-meta-compact">
                    <span class="bpm-display">--.- BPM</span>
                    <span class="key-display">--</span>
                </div>
            `;
        }
        
        const canvas = document.getElementById(`waveformCanvas${deckLabel}`);
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        
        for (let i = 1; i <= 8; i++) {
            const cueTimeEl = document.getElementById(`cueTime${deckLabel}${i}`);
            if (cueTimeEl) {
                cueTimeEl.textContent = '--:--';
            }
            
            const cuePad = document.querySelector(`.hot-cue-pad[data-deck="${deckId}"][data-cue="${i}"]`);
            if (cuePad) {
                cuePad.classList.remove('active');
            }
        }
        
        const cueMarkersEl = document.getElementById(`cueMarkers${deckLabel}`);
        if (cueMarkersEl) {
            cueMarkersEl.innerHTML = '';
        }
    }
    
    onTrackEnded(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        deck.isPlaying = false;
        const playIcon = document.getElementById(`playIcon${deckLabel}`);
        if (playIcon) {
            playIcon.className = 'fas fa-play';
        }
        
        if (deck.animationFrame) {
            cancelAnimationFrame(deck.animationFrame);
        }
    }
    
    updateTrackInfo(deckId) {
        this.updatePlayhead(deckId);
    }
    
    formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '00:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    setPitch(deckId, pitchPercent) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        if (!deck.track) return;
        
        deck.pitchValue = parseFloat(pitchPercent);
        
        const playbackRate = 1 + (deck.pitchValue / 100);
        deck.audio.playbackRate = playbackRate;
        deck.audio.preservesPitch = deck.masterTempo;
        
        const pitchValueEl = document.getElementById(`pitchValue${deckLabel}`);
        if (pitchValueEl) {
            const sign = deck.pitchValue >= 0 ? '+' : '';
            pitchValueEl.textContent = `${sign}${deck.pitchValue.toFixed(1)}%`;
        }
        
        if (deck.track && deck.originalBPM) {
            const currentBPM = deck.originalBPM * playbackRate;
            this.updateBPMDisplay(deckId, currentBPM);
        }
    }
    
    toggleMasterTempo(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        deck.masterTempo = !deck.masterTempo;
        deck.audio.preservesPitch = deck.masterTempo;
        
        const btn = document.getElementById(`masterTempo${deckLabel}`);
        if (btn) {
            if (deck.masterTempo) {
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-lock"></i>';
                btn.title = 'Master Tempo ON (Key Locked)';
            } else {
                btn.classList.remove('active');
                btn.innerHTML = '<i class="fas fa-lock-open"></i>';
                btn.title = 'Master Tempo OFF (Key Changes with Pitch)';
            }
        }
    }
    
    setMasterDeck(deckId) {
        const otherDeckId = deckId === 'a' ? 'b' : 'a';
        
        if (this.masterDeck === deckId) {
            this.masterDeck = null;
            this.updateMasterUI(deckId, false);
            console.log(`[Master] No master deck selected`);
        } else {
            this.masterDeck = deckId;
            this.updateMasterUI(deckId, true);
            this.updateMasterUI(otherDeckId, false);
            console.log(`[Master] Deck ${deckId.toUpperCase()} set as master`);
        }
    }
    
    updateMasterUI(deckId, isMaster) {
        const deckLabel = deckId.toUpperCase();
        const btn = document.getElementById(`masterBtn${deckLabel}`);
        
        if (btn) {
            if (isMaster) {
                btn.classList.add('active');
                btn.title = 'Master Deck (Sync Source)';
            } else {
                btn.classList.remove('active');
                btn.title = 'Set as Master Deck';
            }
        }
    }
    
    syncToMaster(deckId, mode = 'bpm') {
        if (!this.masterDeck) {
            console.warn('[Sync] No master deck selected. Please select a master deck first.');
            alert('Please select a master deck first by clicking the MASTER button on either deck.');
            return;
        }
        
        let sourceDeckId, targetDeckId;
        
        if (deckId === this.masterDeck) {
            sourceDeckId = this.masterDeck;
            targetDeckId = deckId === 'a' ? 'b' : 'a';
            
            if (!this.decks[targetDeckId].track) {
                console.warn(`[Sync] Cannot push sync - Deck ${targetDeckId.toUpperCase()} has no track loaded.`);
                alert(`Cannot sync: Deck ${targetDeckId.toUpperCase()} has no track loaded. Load a track first.`);
                return;
            }
            
            console.log(`[Sync] Pushing master ${sourceDeckId.toUpperCase()} settings to Deck ${targetDeckId.toUpperCase()}`);
        } else {
            sourceDeckId = this.masterDeck;
            targetDeckId = deckId;
            
            console.log(`[Sync] Syncing Deck ${targetDeckId.toUpperCase()} TO master ${sourceDeckId.toUpperCase()}`);
        }
        
        const snapBeats = (mode === 'beat');
        this.syncBPM(sourceDeckId, targetDeckId, snapBeats);
    }
    
    syncBPM(sourceDeckId, targetDeckId, snapBeats = false) {
        const sourceDeck = this.decks[sourceDeckId];
        const targetDeck = this.decks[targetDeckId];
        
        if (!sourceDeck.track || !targetDeck.track) {
            console.warn('Both decks must have tracks loaded to sync BPM');
            return;
        }
        
        if (!sourceDeck.originalBPM || !targetDeck.originalBPM) {
            console.warn('BPM information not available for sync');
            return;
        }
        
        const targetBPM = sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
        const requiredPitchPercent = ((targetBPM / targetDeck.originalBPM) - 1) * 100;
        
        const targetDeckLabel = targetDeckId.toUpperCase();
        const slider = document.getElementById(`pitchSlider${targetDeckLabel}`);
        if (slider) {
            slider.value = requiredPitchPercent.toFixed(1);
            this.setPitch(targetDeckId, requiredPitchPercent);
        }
        
        if (snapBeats) {
            this.snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM);
        }
        
        console.log(`Synced ${targetDeckId.toUpperCase()} (${targetDeck.originalBPM} BPM) to ${sourceDeckId.toUpperCase()} (${targetBPM.toFixed(2)} BPM)${snapBeats ? ' + Beat Grid' : ''}`);
    }
    
    snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM) {
        const sourceDeck = this.decks[sourceDeckId];
        const targetDeck = this.decks[targetDeckId];
        
        if (!sourceDeck.beatgridData || !sourceDeck.beatgridData.beats || sourceDeck.beatgridData.beats.length === 0) {
            console.warn(`[Beat Sync] No beat grid data available for source deck ${sourceDeckId.toUpperCase()}. Beat sync requires analyzed beat grids.`);
            alert(`Beat grid not available for ${sourceDeckId.toUpperCase()}. Beat sync requires tracks analyzed in Rekordbox.`);
            return;
        }
        
        if (!targetDeck.beatgridData || !targetDeck.beatgridData.beats || targetDeck.beatgridData.beats.length === 0) {
            console.warn(`[Beat Sync] No beat grid data available for target deck ${targetDeckId.toUpperCase()}. Beat sync requires analyzed beat grids.`);
            alert(`Beat grid not available for ${targetDeckId.toUpperCase()}. Beat sync requires tracks analyzed in Rekordbox.`);
            return;
        }
        
        const sourceBPM = sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
        const sourceCurrentTime = sourceDeck.audio.currentTime;
        const targetCurrentTime = targetDeck.audio.currentTime;
        
        const sourceBeatLength = 60 / sourceBPM;
        const targetBeatLength = 60 / targetBPM;
        
        let sourceFirstBeatOffset = 0;
        let targetFirstBeatOffset = 0;
        
        const firstBeat = sourceDeck.beatgridData.beats[0];
        sourceFirstBeatOffset = firstBeat.time || 0;
        
        const targetFirstBeat = targetDeck.beatgridData.beats[0];
        targetFirstBeatOffset = targetFirstBeat.time || 0;
        
        const sourceTimeFromFirstBeat = sourceCurrentTime - sourceFirstBeatOffset;
        const targetTimeFromFirstBeat = targetCurrentTime - targetFirstBeatOffset;
        
        const sourceBeatPhase = (sourceTimeFromFirstBeat / sourceBeatLength) % 1;
        const targetBeatPhase = (targetTimeFromFirstBeat / targetBeatLength) % 1;
        
        let phaseDifference = sourceBeatPhase - targetBeatPhase;
        
        if (phaseDifference > 0.5) {
            phaseDifference -= 1;
        } else if (phaseDifference < -0.5) {
            phaseDifference += 1;
        }
        
        const timeAdjustment = phaseDifference * targetBeatLength;
        
        let newTargetTime = targetCurrentTime + timeAdjustment;
        
        newTargetTime = Math.max(0, Math.min(newTargetTime, targetDeck.duration - 0.1));
        
        if (Math.abs(timeAdjustment) > 0.001) {
            targetDeck.audio.currentTime = newTargetTime;
            this.updatePlayhead(targetDeckId);
        }
        
        const adjustmentMs = Math.round(timeAdjustment * 1000);
        const sourcePhasePercent = Math.round(sourceBeatPhase * 100);
        const targetPhasePercent = Math.round(targetBeatPhase * 100);
        
        console.log(`[Beat Sync] Master: ${sourceDeckId.toUpperCase()} (phase: ${sourcePhasePercent}%) → Target: ${targetDeckId.toUpperCase()} (phase: ${targetPhasePercent}%) | Adjustment: ${adjustmentMs}ms | First beats: Source=${sourceFirstBeatOffset.toFixed(3)}s, Target=${targetFirstBeatOffset.toFixed(3)}s`);
    }
    
    updateBPMDisplay(deckId, currentBPM) {
        const deckLabel = deckId.toUpperCase();
        const trackInfo = document.getElementById(`trackInfo${deckLabel}`);
        
        if (trackInfo) {
            const bpmDisplay = trackInfo.querySelector('.bpm-display');
            if (bpmDisplay) {
                bpmDisplay.textContent = `${currentBPM.toFixed(2)} BPM`;
            }
        }
    }
    
    startNudge(deckId, direction) {
        const deck = this.decks[deckId];
        
        if (!deck.track) return;
        
        deck.nudgeActive = true;
        deck.basePitchValue = deck.pitchValue;
        deck.nudgeAmount = direction * 4;
        
        const newPitch = deck.basePitchValue + deck.nudgeAmount;
        const playbackRate = 1 + (newPitch / 100);
        deck.audio.playbackRate = playbackRate;
        deck.audio.preservesPitch = deck.masterTempo;
        
        if (deck.track && deck.originalBPM) {
            const currentBPM = deck.originalBPM * playbackRate;
            this.updateBPMDisplay(deckId, currentBPM);
        }
    }
    
    stopNudge(deckId) {
        const deck = this.decks[deckId];
        
        if (!deck.track || !deck.nudgeActive) return;
        
        deck.nudgeActive = false;
        deck.nudgeAmount = 0;
        
        const playbackRate = 1 + (deck.pitchValue / 100);
        deck.audio.playbackRate = playbackRate;
        deck.audio.preservesPitch = deck.masterTempo;
        
        if (deck.track && deck.originalBPM) {
            const currentBPM = deck.originalBPM * playbackRate;
            this.updateBPMDisplay(deckId, currentBPM);
        }
    }
    
    setVolume(deckId, volumePercent) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        deck.volume = parseFloat(volumePercent);
        
        const volumeValue = deck.volume / 100;
        if (deck.gainNode) {
            deck.gainNode.gain.value = volumeValue;
        }
        
        const volumeValueEl = document.getElementById(`volumeValue${deckLabel}`);
        if (volumeValueEl) {
            volumeValueEl.textContent = `${Math.round(deck.volume)}%`;
        }
    }
    
    toggleQuantize(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        deck.quantizeEnabled = !deck.quantizeEnabled;
        
        const btn = document.getElementById(`quantize${deckLabel}`);
        if (btn) {
            if (deck.quantizeEnabled) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }
        
        console.log(`Quantize ${deck.quantizeEnabled ? 'ON' : 'OFF'} for Deck ${deckLabel}`);
    }
    
    quantizeTime(deckId, targetTime) {
        const deck = this.decks[deckId];
        
        if (!deck.quantizeEnabled || !deck.track || !deck.originalBPM) {
            return targetTime;
        }
        
        const currentBPM = deck.originalBPM * (1 + deck.pitchValue / 100);
        const beatLength = 60 / currentBPM;
        
        let firstBeatOffset = 0;
        if (deck.beatgridData && deck.beatgridData.beats && deck.beatgridData.beats.length > 0) {
            firstBeatOffset = deck.beatgridData.beats[0].time;
        }
        
        const timeFromFirstBeat = targetTime - firstBeatOffset;
        const beatNumber = Math.round(timeFromFirstBeat / beatLength);
        const quantizedTime = firstBeatOffset + (beatNumber * beatLength);
        
        return Math.max(0, Math.min(quantizedTime, deck.duration));
    }
    
    triggerHotCue(deckId, cueNumber) {
        const deck = this.decks[deckId];
        
        if (!deck.track) return;
        
        const cueData = deck.hotCues[cueNumber];
        if (cueData && cueData.time !== undefined) {
            let targetTime = cueData.time;
            
            if (deck.quantizeEnabled) {
                targetTime = this.quantizeTime(deckId, targetTime);
            }
            
            deck.audio.currentTime = targetTime;
            this.updatePlayhead(deckId);
            
            if (!deck.isPlaying) {
                this.togglePlay(deckId);
            }
        }
    }
}

window.addEventListener("DOMContentLoaded", () => {
    window.dualPlayer = new DualPlayer();
});
