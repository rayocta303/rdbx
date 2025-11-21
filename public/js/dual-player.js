class DualPlayer {
    constructor() {
        this.sharedZoomLevel = 16;
        this.masterDeck = null;
        this.notificationTimeout = null;
        
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
            scratchAnimationFrame: null,
            bpmSyncEnabled: false,
            beatSyncEnabled: false
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
    
    showNotification(message, type = 'info', duration = 3000) {
        const notificationArea = document.getElementById('syncNotification');
        if (!notificationArea) return;
        
        if (this.notificationTimeout) {
            clearTimeout(this.notificationTimeout);
        }
        
        const icons = {
            info: 'fa-info-circle',
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-exclamation-circle'
        };
        
        const icon = icons[type] || icons.info;
        
        notificationArea.innerHTML = `
            <div class="sync-notification ${type}">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
        `;
        
        this.notificationTimeout = setTimeout(() => {
            notificationArea.innerHTML = '';
        }, duration);
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
            const pitchMultiplier = 1 + (deck.pitchValue / 100);
            const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
            const visibleDuration = deck.duration / effectiveZoom;
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
            
            const displayWidth = container.clientWidth;
            const pitchMultiplier = 1 + (deck.pitchValue / 100);
            const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
            const pixelsPerSecond = displayWidth / (deck.duration / effectiveZoom);
            const deltaTime = deltaX / pixelsPerSecond;
            const visibleDuration = deck.duration / effectiveZoom;
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
        
        console.log(`[Deck ${deckId.toUpperCase()}] Beat grid data:`, {
            has_beat_grid: !!track.beat_grid,
            is_array: Array.isArray(track.beat_grid),
            length: track.beat_grid ? track.beat_grid.length : 0,
            first_beat: track.beat_grid && track.beat_grid.length > 0 ? track.beat_grid[0] : null
        });
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
                if (cue.type === 'cue' && cue.hot_cue !== null && cue.hot_cue !== undefined && cue.hot_cue_label) {
                    const padNumber = cue.hot_cue + 1;
                    const timeInSeconds = cue.time / 1000;
                    
                    deck.hotCues[padNumber] = {
                        time: timeInSeconds,
                        label: cue.comment || `Cue ${cue.hot_cue_label}`
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
                
                this.showNotification(`Failed to play audio: ${error.message}`, 'error', 5000);
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
    
    drawRoundedBar(ctx, x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
        ctx.fill();
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
        const pitchMultiplier = 1 + (deck.pitchValue / 100);
        const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
        const visibleDuration = deck.duration / effectiveZoom;
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
        const dpr = window.devicePixelRatio || 1;
        const displayWidth = container.clientWidth;
        const displayHeight = 120;
        
        canvas.width = displayWidth * dpr;
        canvas.height = displayHeight * dpr;
        canvas.style.width = displayWidth + 'px';
        canvas.style.height = displayHeight + 'px';
        
        const ctx = canvas.getContext('2d', { 
            alpha: false,
            desynchronized: true,
            willReadFrequently: false
        });
        
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(dpr, dpr);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        
        ctx.fillStyle = '#0a0a0a';
        ctx.fillRect(0, 0, displayWidth, displayHeight);
        
        if (!deck.waveformData) {
            ctx.fillStyle = '#333';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No waveform data', displayWidth / 2, displayHeight / 2);
            return;
        }
        
        const pitchMultiplier = 1 + (deck.pitchValue / 100);
        const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
        
        const viewStart = deck.waveformOffset;
        const viewDuration = deck.duration / effectiveZoom;
        const viewEnd = viewStart + viewDuration;
        const leadingBlankDuration = Math.max(0, -viewStart);
        const leadingBlankWidth = (leadingBlankDuration / viewDuration) * displayWidth;
        
        const actualStart = Math.max(0, viewStart);
        const startIndex = Math.floor((actualStart / deck.duration) * deck.waveformData.length);
        const endIndex = Math.ceil((viewEnd / deck.duration) * deck.waveformData.length);
        const visibleData = deck.waveformData.slice(startIndex, endIndex);
        
        if (visibleData.length === 0) return;
        
        const dataWidth = displayWidth - leadingBlankWidth;
        const samplesPerPixel = visibleData.length / dataWidth;
        const height = displayHeight;
        const isColorWaveform = visibleData[0] && visibleData[0].r !== undefined;
        
        for (let x = Math.floor(leadingBlankWidth); x < displayWidth; x++) {
            const pixelOffset = x - leadingBlankWidth;
            const sampleStart = Math.floor(pixelOffset * samplesPerPixel);
            const sampleEnd = Math.ceil((pixelOffset + 1) * samplesPerPixel);
            
            if (sampleStart >= visibleData.length) break;
            
            let maxHeight = 0;
            let maxR = 0, maxG = 0, maxB = 0;
            
            const endIdx = Math.min(visibleData.length, sampleEnd);
            for (let i = sampleStart; i < endIdx; i++) {
                const sample = visibleData[i];
                if (!sample) continue;
                
                maxHeight = Math.max(maxHeight, sample.height || 0);
                if (isColorWaveform) {
                    maxR = Math.max(maxR, sample.r || 0);
                    maxG = Math.max(maxG, sample.g || 0);
                    maxB = Math.max(maxB, sample.b || 0);
                }
            }
            
            if (maxHeight === 0) continue;
            
            const normalizedHeight = maxHeight / 255;
            const barHeight = normalizedHeight * height * 0.9;
            const y = (height - barHeight) / 2;
            const barWidth = 1.2;
            const radius = Math.min(barWidth / 2, 1.5);
            
            if (isColorWaveform) {
                const brightness = Math.max(maxR, maxG, maxB) / 255;
                ctx.fillStyle = `rgba(${maxR}, ${maxG}, ${maxB}, ${0.85 + brightness * 0.15})`;
            } else {
                const intensity = normalizedHeight;
                ctx.fillStyle = `rgba(0, 217, 255, ${0.75 + intensity * 0.25})`;
            }
            
            if (barHeight > radius * 2) {
                this.drawRoundedBar(ctx, x - barWidth / 2, y, barWidth, barHeight, radius);
            } else {
                ctx.fillRect(x - barWidth / 2, y, barWidth, barHeight);
            }
        }
        
        this.renderBeatgrid(deckId, ctx, displayWidth, displayHeight, viewStart, viewDuration);
    }
    
    renderBeatgrid(deckId, ctx, width, height, viewStart, viewDuration) {
        const deck = this.decks[deckId];
        
        if (!deck.track || !deck.track.bpm || deck.track.bpm <= 0) return;
        
        const pitchMultiplier = 1 + (deck.pitchValue / 100);
        const viewEnd = viewStart + viewDuration;
        
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
        ctx.lineWidth = 4;
        
        // Use PQTZ beat grid data if available, otherwise fallback to BPM calculation
        if (deck.beatgridData && Array.isArray(deck.beatgridData) && deck.beatgridData.length > 0) {
            // Iterate over actual PQTZ beat timestamps
            // No need to scale beat.time - viewDuration already handles pitch stretching
            deck.beatgridData.forEach(beat => {
                // Use raw beat time from PQTZ
                const beatTime = beat.time;
                
                // Only render beats within visible view
                if (beatTime >= viewStart && beatTime < viewEnd) {
                    const relativePosition = (beatTime - viewStart) / viewDuration;
                    const x = relativePosition * width;
                    
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, height);
                    ctx.stroke();
                }
            });
        } else {
            // Fallback: Use simple BPM calculation when no beat grid data available
            const effectiveBPM = deck.track.bpm * pitchMultiplier;
            const beatInterval = 60 / effectiveBPM;
            const firstBeat = Math.ceil(viewStart / beatInterval) * beatInterval;
            
            for (let beatTime = firstBeat; beatTime < viewEnd; beatTime += beatInterval) {
                const relativePosition = (beatTime - viewStart) / viewDuration;
                const x = relativePosition * width;
                
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, height);
                ctx.stroke();
            }
        }
    }
    
    renderCueMarkers(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const container = document.getElementById(`cueMarkers${deckLabel}`);
        
        if (!container || !deck.duration || deck.duration <= 0) return;
        
        container.innerHTML = '';
        
        const pitchMultiplier = 1 + (deck.pitchValue / 100);
        const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
        const viewStart = deck.waveformOffset;
        const viewDuration = deck.duration / effectiveZoom;
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
    
    setPitch(deckId, pitchPercent, skipBPMSync = false) {
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
        
        // Re-render waveform and beat grid to reflect new pitch/tempo
        this.renderWaveform(deckId);
        this.renderCueMarkers(deckId);
        
        // Auto-sync BPM to other deck if BPM sync is enabled
        if (!skipBPMSync && deck.bpmSyncEnabled && this.masterDeck === deckId) {
            const otherDeckId = deckId === 'a' ? 'b' : 'a';
            const otherDeck = this.decks[otherDeckId];
            
            if (otherDeck.track && otherDeck.originalBPM && otherDeck.bpmSyncEnabled) {
                const targetBPM = deck.originalBPM * playbackRate;
                const requiredPitchPercent = ((targetBPM / otherDeck.originalBPM) - 1) * 100;
                
                const otherDeckLabel = otherDeckId.toUpperCase();
                const otherSlider = document.getElementById(`pitchSlider${otherDeckLabel}`);
                if (otherSlider) {
                    otherSlider.value = requiredPitchPercent.toFixed(1);
                    this.setPitch(otherDeckId, requiredPitchPercent, true); // skipBPMSync to avoid loop
                }
            }
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
            this.showNotification('Please select a master deck first by clicking the MASTER button on either deck.', 'warning', 3000);
            return;
        }
        
        let sourceDeckId, targetDeckId;
        
        if (deckId === this.masterDeck) {
            sourceDeckId = this.masterDeck;
            targetDeckId = deckId === 'a' ? 'b' : 'a';
            
            if (!this.decks[targetDeckId].track) {
                console.warn(`[Sync] Cannot push sync - Deck ${targetDeckId.toUpperCase()} has no track loaded.`);
                this.showNotification(`Cannot sync: Deck ${targetDeckId.toUpperCase()} has no track loaded. Load a track first.`, 'warning', 3000);
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
        
        if (!sourceDeck.beatgridData || !Array.isArray(sourceDeck.beatgridData) || sourceDeck.beatgridData.length === 0) {
            console.warn(`[Beat Sync] No beat grid data available for source deck ${sourceDeckId.toUpperCase()}. Beat sync requires analyzed beat grids.`);
            this.showNotification(`Beat grid not available for ${sourceDeckId.toUpperCase()}. Beat sync requires tracks analyzed in Rekordbox.`, 'error', 4000);
            return;
        }
        
        if (!targetDeck.beatgridData || !Array.isArray(targetDeck.beatgridData) || targetDeck.beatgridData.length === 0) {
            console.warn(`[Beat Sync] No beat grid data available for target deck ${targetDeckId.toUpperCase()}. Beat sync requires analyzed beat grids.`);
            this.showNotification(`Beat grid not available for ${targetDeckId.toUpperCase()}. Beat sync requires tracks analyzed in Rekordbox.`, 'error', 4000);
            return;
        }
        
        const sourceBPM = sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
        const sourceCurrentTime = sourceDeck.audio.currentTime;
        const targetCurrentTime = targetDeck.audio.currentTime;
        
        const sourceBeatLength = 60 / sourceBPM;
        const targetBeatLength = 60 / targetBPM;
        
        let sourceFirstBeatOffset = 0;
        let targetFirstBeatOffset = 0;
        
        const firstBeat = sourceDeck.beatgridData[0];
        sourceFirstBeatOffset = firstBeat.time || 0;
        
        const targetFirstBeat = targetDeck.beatgridData[0];
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
    
    toggleBPMSync(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const otherDeckId = deckId === 'a' ? 'b' : 'a';
        const otherDeck = this.decks[otherDeckId];
        
        // Toggle BPM sync state
        deck.bpmSyncEnabled = !deck.bpmSyncEnabled;
        
        // Auto-enable master deck if not set and both decks have tracks
        if (deck.bpmSyncEnabled && !this.masterDeck) {
            if (deck.track && otherDeck.track) {
                // Set current deck as master
                this.masterDeck = deckId;
                this.updateMasterUI(deckId, true);
                this.updateMasterUI(otherDeckId, false);
                this.showNotification(`Master deck auto-set to ${deckLabel}`, 'info', 2000);
                console.log(`[BPM Sync] Auto-set master deck to ${deckLabel}`);
            }
        }
        
        // Update button UI
        const btn = document.getElementById(`bpmSync${deckLabel}`);
        if (btn) {
            if (deck.bpmSyncEnabled) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }
        
        // If enabling sync, sync BPM now
        if (deck.bpmSyncEnabled && this.masterDeck) {
            this.syncToMaster(deckId, 'bpm');
        }
        
        console.log(`BPM Sync ${deck.bpmSyncEnabled ? 'ON' : 'OFF'} for Deck ${deckLabel}`);
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
        if (deck.beatgridData && Array.isArray(deck.beatgridData) && deck.beatgridData.length > 0) {
            firstBeatOffset = deck.beatgridData[0].time;
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
