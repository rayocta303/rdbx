class DualPlayer {
    constructor() {
        this.decks = {
            a: this.createDeck('a'),
            b: this.createDeck('b')
        };
        
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        this.initializeDecks();
    }
    
    createDeck(deckId) {
        return {
            id: deckId,
            track: null,
            audio: new Audio(),
            source: null,
            gainNode: null,
            isPlaying: false,
            currentTime: 0,
            duration: 0,
            zoomLevel: 16,
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
            quantizeEnabled: false
        };
    }
    
    initializeDecks() {
        ['a', 'b'].forEach(deckId => {
            const deck = this.decks[deckId];
            
            deck.gainNode = this.audioContext.createGain();
            deck.gainNode.connect(this.audioContext.destination);
            
            deck.audio.addEventListener('loadeddata', () => {
                if (!deck.source) {
                    try {
                        deck.source = this.audioContext.createMediaElementSource(deck.audio);
                        deck.source.connect(deck.gainNode);
                    } catch (e) {
                        console.warn('Audio context already connected for deck', deckId);
                    }
                }
            });
            
            deck.audio.addEventListener('timeupdate', () => this.updatePlayhead(deckId));
            deck.audio.addEventListener('loadedmetadata', () => this.onTrackLoaded(deckId));
            deck.audio.addEventListener('ended', () => this.onTrackEnded(deckId));
            
            this.setupWaveformInteraction(deckId);
        });
    }
    
    setupWaveformInteraction(deckId) {
        const canvas = document.getElementById(`waveformCanvas${deckId.toUpperCase()}`);
        const container = document.getElementById(`waveformContainer${deckId.toUpperCase()}`);
        
        if (!canvas || !container) return;
        
        let isDragging = false;
        let startX = 0;
        let startOffset = 0;
        
        container.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.clientX;
            startOffset = this.decks[deckId].waveformOffset;
            container.style.cursor = 'grabbing';
        });
        
        container.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            
            const deck = this.decks[deckId];
            if (!deck.duration || deck.duration <= 0) return;
            
            const deltaX = e.clientX - startX;
            const pixelsPerSecond = canvas.width / (deck.duration / deck.zoomLevel);
            const deltaTime = deltaX / pixelsPerSecond;
            const visibleDuration = deck.duration / deck.zoomLevel;
            const minOffset = -visibleDuration / 2;
            
            deck.waveformOffset = startOffset - deltaTime;
            deck.waveformOffset = Math.max(minOffset, Math.min(deck.waveformOffset, deck.duration - visibleDuration));
            
            this.renderWaveform(deckId);
            this.renderCueMarkers(deckId);
        });
        
        container.addEventListener('mouseup', () => {
            isDragging = false;
            container.style.cursor = 'grab';
        });
        
        container.addEventListener('mouseleave', () => {
            isDragging = false;
            container.style.cursor = 'grab';
        });
        
        container.addEventListener('click', (e) => {
            if (isDragging) return;
            
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const percentage = x / rect.width;
            const time = (this.decks[deckId].waveformOffset + (this.decks[deckId].duration / this.decks[deckId].zoomLevel) * percentage);
            
            if (this.decks[deckId].track) {
                this.decks[deckId].audio.currentTime = Math.min(time, this.decks[deckId].duration);
            }
        });
    }
    
    loadTrack(track, deckId) {
        const deck = this.decks[deckId];
        
        if (deck.isPlaying) {
            this.togglePlay(deckId);
        }
        
        deck.track = track;
        deck.audio.src = `/audio.php?path=${encodeURIComponent(track.file_path)}`;
        
        if (track.waveform) {
            deck.waveformData = track.waveform.color_data || track.waveform.preview_data || null;
        } else {
            deck.waveformData = null;
        }
        
        deck.beatgridData = track.beat_grid;
        deck.zoomLevel = 16;
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
        
        if (deck.duration > 0) {
            const visibleDuration = deck.duration / deck.zoomLevel;
            deck.waveformOffset = -visibleDuration / 2;
            
            const zoomLevelEl = document.getElementById(`zoomLevel${deckLabel}`);
            if (zoomLevelEl) {
                zoomLevelEl.textContent = `${deck.zoomLevel}x`;
            }
            
            this.renderWaveform(deckId);
            this.renderCueMarkers(deckId);
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
    
    togglePlay(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const playIcon = document.getElementById(`playIcon${deckLabel}`);
        
        if (!deck.track) return;
        
        if (deck.isPlaying) {
            deck.audio.pause();
            deck.isPlaying = false;
            playIcon.className = 'fas fa-play';
            if (deck.animationFrame) {
                cancelAnimationFrame(deck.animationFrame);
            }
        } else {
            deck.audio.play();
            deck.isPlaying = true;
            playIcon.className = 'fas fa-pause';
            this.startPlayheadAnimation(deckId);
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
        
        const visibleDuration = duration / deck.zoomLevel;
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
    
    zoomWaveform(deckId, direction) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        
        if (!deck.duration || deck.duration <= 0) return;
        
        const zoomLevels = [1, 2, 4, 8, 16, 32, 64];
        let currentIndex = zoomLevels.indexOf(deck.zoomLevel);
        
        if (direction > 0 && currentIndex < zoomLevels.length - 1) {
            currentIndex++;
        } else if (direction < 0 && currentIndex > 0) {
            currentIndex--;
        }
        
        deck.zoomLevel = zoomLevels[currentIndex];
        
        const visibleDuration = deck.duration / deck.zoomLevel;
        const minOffset = -visibleDuration / 2;
        deck.waveformOffset = Math.max(minOffset, Math.min(deck.waveformOffset, deck.duration - visibleDuration));
        
        const zoomLevelEl = document.getElementById(`zoomLevel${deckLabel}`);
        if (zoomLevelEl) {
            zoomLevelEl.textContent = `${deck.zoomLevel}x`;
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
        
        const ctx = canvas.getContext('2d');
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
        const viewDuration = deck.duration / deck.zoomLevel;
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
            const barHeight = (sample.height / 255) * height * 0.85;
            const y = (height - barHeight) / 2;
            
            if (isColorWaveform) {
                const brightness = Math.max(sample.r, sample.g, sample.b) / 255;
                ctx.fillStyle = `rgba(${sample.r}, ${sample.g}, ${sample.b}, ${0.8 + brightness * 0.2})`;
            } else {
                const intensity = sample.height / 255;
                ctx.fillStyle = `rgba(0, 217, 255, ${0.7 + intensity * 0.3})`;
            }
            
            ctx.fillRect(x, y, Math.max(1, step), barHeight);
        });
        
        this.renderBeatgrid(deckId, ctx, canvas.width, canvas.height, viewStart, viewDuration);
    }
    
    renderBeatgrid(deckId, ctx, width, height, viewStart, viewDuration) {
        const deck = this.decks[deckId];
        
        if (!deck.track || !deck.track.bpm || deck.track.bpm <= 0) return;
        
        const beatInterval = 60 / deck.track.bpm;
        const viewEnd = viewStart + viewDuration;
        
        const firstBeat = Math.ceil(viewStart / beatInterval) * beatInterval;
        
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 2;
        
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
        const viewDuration = deck.duration / deck.zoomLevel;
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
        deck.zoomLevel = 1;
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
        
        const sourceBPM = sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
        const sourceCurrentTime = sourceDeck.audio.currentTime;
        const targetCurrentTime = targetDeck.audio.currentTime;
        
        const sourceBeatLength = 60 / sourceBPM;
        const targetBeatLength = 60 / targetBPM;
        
        let sourceFirstBeatOffset = 0;
        let targetFirstBeatOffset = 0;
        
        if (sourceDeck.beatgridData && sourceDeck.beatgridData.beats && sourceDeck.beatgridData.beats.length > 0) {
            sourceFirstBeatOffset = sourceDeck.beatgridData.beats[0].time;
        }
        
        if (targetDeck.beatgridData && targetDeck.beatgridData.beats && targetDeck.beatgridData.beats.length > 0) {
            targetFirstBeatOffset = targetDeck.beatgridData.beats[0].time;
        }
        
        const sourceTimeFromFirstBeat = sourceCurrentTime - sourceFirstBeatOffset;
        const targetTimeFromFirstBeat = targetCurrentTime - targetFirstBeatOffset;
        
        const sourceBeatPhase = (sourceTimeFromFirstBeat / sourceBeatLength) % 1;
        const targetBeatPhase = (targetTimeFromFirstBeat / targetBeatLength) % 1;
        
        const phaseDifference = sourceBeatPhase - targetBeatPhase;
        const timeAdjustment = phaseDifference * targetBeatLength;
        
        let newTargetTime = targetCurrentTime + timeAdjustment;
        
        if (newTargetTime < 0) {
            newTargetTime += targetBeatLength;
        } else if (newTargetTime >= targetDeck.duration) {
            newTargetTime -= targetBeatLength;
        }
        
        targetDeck.audio.currentTime = newTargetTime;
        this.updatePlayhead(targetDeckId);
        
        console.log(`Beat Grid Snapped: Source offset=${sourceFirstBeatOffset.toFixed(3)}s, Target offset=${targetFirstBeatOffset.toFixed(3)}s, Phase diff=${(phaseDifference * 100).toFixed(1)}%, Adjustment=${(timeAdjustment * 1000).toFixed(0)}ms`);
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
