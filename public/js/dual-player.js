class DualPlayer {
    constructor() {
        this.sharedZoomLevel = 16;
        this.masterDeck = null;
        this.notificationTimeout = null;

        this.decks = {
            a: this.createDeck("a"),
            b: this.createDeck("b"),
        };

        try {
            this.audioContext = new (window.AudioContext ||
                window.webkitAudioContext)();
            console.log("[DualPlayer] Audio context initialized successfully");
        } catch (e) {
            console.error(
                "[DualPlayer] Failed to initialize audio context:",
                e,
            );
        }

        this.initializeDecks();
    }

    createDeck(deckId) {
        const audio = new Audio();
        audio.crossOrigin = "anonymous";
        audio.preload = "auto";

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
            beatSyncEnabled: false,
        };
    }

    initializeDecks() {
        ["a", "b"].forEach((deckId) => {
            const deck = this.decks[deckId];

            deck.gainNode = this.audioContext.createGain();
            deck.gainNode.connect(this.audioContext.destination);

            deck.audio.addEventListener("loadeddata", () => {
                console.log(
                    `[Deck ${deckId.toUpperCase()}] Audio data loaded successfully`,
                );
                if (!deck.source) {
                    try {
                        deck.source =
                            this.audioContext.createMediaElementSource(
                                deck.audio,
                            );
                        deck.source.connect(deck.gainNode);
                        console.log(
                            `[Deck ${deckId.toUpperCase()}] Audio source connected to gain node`,
                        );
                    } catch (e) {
                        console.warn(
                            `[Deck ${deckId.toUpperCase()}] Audio context already connected:`,
                            e,
                        );
                    }
                }
            });

            deck.audio.addEventListener("timeupdate", () =>
                this.updatePlayhead(deckId),
            );
            deck.audio.addEventListener("loadedmetadata", () =>
                this.onTrackLoaded(deckId),
            );
            deck.audio.addEventListener("ended", () =>
                this.onTrackEnded(deckId),
            );

            deck.audio.addEventListener("error", (e) => {
                const error = deck.audio.error;
                console.error(`[Deck ${deckId.toUpperCase()}] Audio error:`, {
                    code: error?.code,
                    message: error?.message,
                    src: deck.audio.src,
                    readyState: deck.audio.readyState,
                    networkState: deck.audio.networkState,
                });

                const errorMessages = {
                    1: "MEDIA_ERR_ABORTED - The fetching process was aborted by the user",
                    2: "MEDIA_ERR_NETWORK - A network error occurred while fetching",
                    3: "MEDIA_ERR_DECODE - Error occurred while decoding the media",
                    4: "MEDIA_ERR_SRC_NOT_SUPPORTED - The media format is not supported",
                };

                console.error(
                    `[Deck ${deckId.toUpperCase()}] ${errorMessages[error?.code] || "Unknown error"}`,
                );
            });

            deck.audio.addEventListener("canplay", () => {
                console.log(
                    `[Deck ${deckId.toUpperCase()}] Audio can start playing (canplay event)`,
                );
            });

            deck.audio.addEventListener("canplaythrough", () => {
                console.log(
                    `[Deck ${deckId.toUpperCase()}] Audio can play through without buffering (canplaythrough event)`,
                );
            });

            this.setupWaveformInteraction(deckId);
        });
    }

    showNotification(message, type = "info", duration = 3000) {
        const notificationArea = document.getElementById("syncNotification");
        if (!notificationArea) return;

        if (this.notificationTimeout) {
            clearTimeout(this.notificationTimeout);
        }

        const icons = {
            info: "fa-info-circle",
            success: "fa-check-circle",
            warning: "fa-exclamation-triangle",
            error: "fa-exclamation-circle",
        };

        const icon = icons[type] || icons.info;

        notificationArea.innerHTML = `
            <div class="sync-notification ${type}">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
        `;

        this.notificationTimeout = setTimeout(() => {
            notificationArea.innerHTML = "";
        }, duration);
    }

    setupWaveformInteraction(deckId) {
        const canvas = document.getElementById(
            `waveformCanvas${deckId.toUpperCase()}`,
        );
        const container = document.getElementById(
            `waveformContainer${deckId.toUpperCase()}`,
        );

        if (!canvas || !container) return;

        let isDragging = false;
        let hasDragged = false;
        let startX = 0;
        let startCenter = 0;
        let lastX = 0;
        let lastCenter = 0;
        let lastTime = 0;
        let wasPlaying = false;

        container.style.cursor = "grab";

        container.addEventListener("mousedown", (e) => {
            const deck = this.decks[deckId];
            const pitchMultiplier = 1 + deck.pitchValue / 100;
            const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
            const visibleDuration = deck.duration / effectiveZoom;
            isDragging = true;
            hasDragged = false;
            startX = e.clientX;
            lastX = e.clientX;
            startCenter = deck.waveformOffset + visibleDuration / 2;
            lastCenter = startCenter;
            wasPlaying = deck.isPlaying;
            lastTime = Date.now();
            container.style.cursor = "grabbing";
        });

        container.addEventListener("mousemove", (e) => {
            if (!isDragging) return;

            const deck = this.decks[deckId];
            if (!deck.duration || deck.duration <= 0) return;

            const deltaX = e.clientX - startX;

            if (Math.abs(deltaX) > 3) {
                hasDragged = true;
            }

            const displayWidth = container.clientWidth;
            const pitchMultiplier = 1 + deck.pitchValue / 100;
            const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
            const pixelsPerSecond =
                displayWidth / (deck.duration / effectiveZoom);
            const deltaTime = deltaX / pixelsPerSecond;
            const visibleDuration = deck.duration / effectiveZoom;
            const minOffset = -visibleDuration / 2;

            const newCenter = startCenter - deltaTime;
            deck.waveformOffset = newCenter - visibleDuration / 2;
            deck.waveformOffset = Math.max(
                minOffset,
                Math.min(deck.waveformOffset, deck.duration - visibleDuration),
            );

            const centerTime = deck.waveformOffset + visibleDuration / 2;
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
                                cancelAnimationFrame(
                                    deck.scratchAnimationFrame,
                                );
                                deck.scratchAnimationFrame = null;
                            }
                            if (deck.audio.paused) {
                                deck.audio.play();
                            }
                            const clampedRate = Math.max(
                                0.25,
                                Math.min(4, deck.scratchSpeed),
                            );
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
            const timeDisplay = document.getElementById(
                `timeDisplay${deckLabel}`,
            );
            if (timeDisplay) {
                timeDisplay.textContent = `${this.formatTime(targetTime)} / ${this.formatTime(deck.duration)}`;
            }
        });

        const endDrag = () => {
            if (!isDragging) return;

            const deck = this.decks[deckId];
            isDragging = false;
            container.style.cursor = "grab";

            deck.isScratching = false;
            if (deck.scratchAnimationFrame) {
                cancelAnimationFrame(deck.scratchAnimationFrame);
                deck.scratchAnimationFrame = null;
            }

            if (deck.audio) {
                const normalRate = 1.0 + deck.pitchValue / 100;
                deck.audio.playbackRate = Math.max(
                    0.25,
                    Math.min(4, normalRate),
                );

                if (wasPlaying && deck.audio.paused) {
                    deck.audio.play();
                }
            }
        };

        container.addEventListener("mouseup", endDrag);
        container.addEventListener("mouseleave", endDrag);

        container.addEventListener(
            "wheel",
            (e) => {
                e.preventDefault();

                const deck = this.decks[deckId];
                if (!deck.duration || deck.duration <= 0) return;

                const direction = e.deltaY < 0 ? 1 : -1;
                this.zoomBothDecks(direction);
            },
            { passive: false },
        );
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
            duration: track.duration,
        });

        if (deck.isPlaying) {
            this.togglePlay(deckId);
        }

        deck.track = track;
        const audioSrc = `/audio.php?path=${encodeURIComponent(track.file_path)}`;
        console.log(
            `[Deck ${deckId.toUpperCase()}] Audio source URL:`,
            audioSrc,
        );

        deck.audio.src = audioSrc;
        deck.audio.load();

        if (track.waveform) {
            deck.waveformData = track.waveform;
            console.log(
                `[Deck ${deckId.toUpperCase()}] Waveform data available:`,
                {
                    has_three_band_preview: !!track.waveform.three_band_preview,
                    has_three_band_detail: !!track.waveform.three_band_detail,
                    has_color_data: !!track.waveform.color_data,
                    has_preview_data: !!track.waveform.preview_data,
                },
            );
        } else {
            deck.waveformData = null;
            console.log(
                `[Deck ${deckId.toUpperCase()}] No waveform data available`,
            );
        }

        deck.beatgridData = track.beat_grid;

        console.log(`[Deck ${deckId.toUpperCase()}] Beat grid data:`, {
            has_beat_grid: !!track.beat_grid,
            is_array: Array.isArray(track.beat_grid),
            length: track.beat_grid ? track.beat_grid.length : 0,
            first_beat:
                track.beat_grid && track.beat_grid.length > 0
                    ? track.beat_grid[0]
                    : null,
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
            pitchValue.textContent = "0.0%";
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

        console.log(
            `[Deck ${deckLabel}] Track metadata loaded - Duration: ${deck.duration.toFixed(2)}s`,
        );

        if (deck.duration > 0) {
            const visibleDuration = deck.duration / this.sharedZoomLevel;
            const currentTime = deck.audio.currentTime || 0;
            deck.waveformOffset = currentTime - visibleDuration / 2;

            const sharedZoomEl = document.getElementById("sharedZoomLevel");
            if (sharedZoomEl) {
                sharedZoomEl.textContent = `${this.sharedZoomLevel}x`;
            }

            console.log(
                `[Deck ${deckLabel}] Rendering waveform with zoom level: ${this.sharedZoomLevel}x, offset: ${deck.waveformOffset.toFixed(2)}s`,
            );
            this.renderWaveform(deckId);
            this.renderCueMarkers(deckId);
        } else {
            console.warn(
                `[Deck ${deckLabel}] Invalid duration: ${deck.duration}`,
            );
        }

        this.updateTrackInfo(deckId);
    }

    loadHotCues(track, deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        deck.hotCues = {};

        // Reset all hot cue pads (0-7 for A-H)
        for (let i = 0; i < 8; i++) {
            const cueTimeEl = document.getElementById(
                `cueTime${deckLabel}${i}`,
            );
            if (cueTimeEl) {
                cueTimeEl.textContent = "--:--";
            }

            const cuePad = document.querySelector(
                `.hot-cue-pad[data-deck="${deckId}"][data-cue="${i}"]`,
            );
            if (cuePad) {
                cuePad.classList.remove("active");
            }
        }

        if (track.cue_points && track.cue_points.length > 0) {
            track.cue_points.forEach((cue) => {
                // hot_cue is 0-based: 0=A, 1=B, 2=C, etc. Only process valid hot cues (0-7)
                if (
                    cue.type === "cue" &&
                    cue.hot_cue !== null &&
                    cue.hot_cue !== undefined &&
                    cue.hot_cue >= 0 &&
                    cue.hot_cue < 8
                ) {
                    const hotCueIndex = cue.hot_cue; // Use 0-based index directly
                    const timeInSeconds = cue.time / 1000;

                    deck.hotCues[hotCueIndex] = {
                        time: timeInSeconds,
                        label: cue.comment || `Cue ${cue.hot_cue_label}`,
                    };

                    const cueTimeEl = document.getElementById(
                        `cueTime${deckLabel}${hotCueIndex}`,
                    );
                    if (cueTimeEl) {
                        cueTimeEl.textContent = this.formatTime(timeInSeconds);
                    }

                    const cuePad = document.querySelector(
                        `.hot-cue-pad[data-deck="${deckId}"][data-cue="${hotCueIndex}"]`,
                    );
                    if (cuePad) {
                        cuePad.classList.add("active");
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
            console.log(
                `[Deck ${deckLabel}] Pausing playback at ${deck.audio.currentTime.toFixed(2)}s`,
            );
            deck.audio.pause();
            deck.isPlaying = false;
            playIcon.className = "fas fa-play";
            if (deck.animationFrame) {
                cancelAnimationFrame(deck.animationFrame);
            }
        } else {
            console.log(
                `[Deck ${deckLabel}] Starting playback from ${deck.audio.currentTime.toFixed(2)}s`,
            );

            if (this.audioContext && this.audioContext.state === "suspended") {
                console.log(`[Deck ${deckLabel}] Resuming AudioContext...`);
                try {
                    await this.audioContext.resume();
                    console.log(
                        `[Deck ${deckLabel}] AudioContext resumed, state: ${this.audioContext.state}`,
                    );
                } catch (err) {
                    console.error(
                        `[Deck ${deckLabel}] Failed to resume AudioContext:`,
                        err,
                    );
                }
            }

            try {
                await deck.audio.play();
                console.log(
                    `[Deck ${deckLabel}] Playback started successfully`,
                );
                deck.isPlaying = true;
                playIcon.className = "fas fa-pause";
                this.startPlayheadAnimation(deckId);
            } catch (error) {
                console.error(`[Deck ${deckLabel}] Playback failed:`, error);
                console.error(`[Deck ${deckLabel}] Error details:`, {
                    name: error.name,
                    message: error.message,
                    audioSrc: deck.audio.src,
                    readyState: deck.audio.readyState,
                    networkState: deck.audio.networkState,
                    audioContextState: this.audioContext?.state,
                });
                deck.isPlaying = false;
                playIcon.className = "fas fa-play";

                this.showNotification(
                    `Failed to play audio: ${error.message}`,
                    "error",
                    5000,
                );
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
        deck.waveformOffset = Math.max(
            minOffset,
            Math.min(
                currentTime - visibleDuration / 2,
                duration - visibleDuration,
            ),
        );

        const container = document.getElementById(
            `waveformContainer${deckLabel}`,
        );
        if (container) {
            const playhead = container.querySelector(".playhead");
            if (playhead) {
                playhead.style.left = "50%";
            }
        }

        const timeDisplay = document.getElementById(`timeDisplay${deckLabel}`);
        if (timeDisplay) {
            timeDisplay.textContent = `${this.formatTime(currentTime)} / ${this.formatTime(duration)}`;
        }

        this.renderWaveform(deckId);
        this.renderCueMarkers(deckId);
    }

    draw3BandWaveformMirror(ctx, data, W, H) {
        const N = Math.min(data.length, Math.floor(W));
        const w = W / N;

        const low = new Float32Array(N);
        const mid = new Float32Array(N);
        const high = new Float32Array(N);

        const samplesPerPixel = data.length / N;

        for (let i = 0; i < N; i++) {
            if (samplesPerPixel > 1) {
                const start = Math.floor(i * samplesPerPixel);
                const end = Math.min(
                    data.length,
                    Math.ceil((i + 1) * samplesPerPixel),
                );

                let maxL = 0,
                    maxM = 0,
                    maxH = 0;
                for (let j = start; j < end; j++) {
                    maxL = Math.max(maxL, (data[j].low || 0) / 255);
                    maxM = Math.max(maxM, (data[j].mid || 0) / 255);
                    maxH = Math.max(maxH, (data[j].high || 0) / 255);
                }
                low[i] = maxL;
                mid[i] = maxM;
                high[i] = maxH;
            } else {
                low[i] = (data[i]?.low || 0) / 255;
                mid[i] = (data[i]?.mid || 0) / 255;
                high[i] = (data[i]?.high || 0) / 255;
            }
        }

        const config = {
            HEIGHT_RATIO: 0.48,
            SCALE_LOW: 1.0,
            SCALE_MID: 0.85,
            SCALE_HIGH: 0.7,
            SCALE_CORE: 0.3,
            CORE_BOOST: 1.7,
        };

        this.drawBandMirror(
            ctx,
            low,
            "#ffffff",
            "#ffffff",
            config.SCALE_LOW,
            N,
            w,
            H,
            config.HEIGHT_RATIO,
        );
        this.drawBandMirror(
            ctx,
            mid,
            "#ffa600",
            "#ffa600",
            config.SCALE_MID,
            N,
            w,
            H,
            config.HEIGHT_RATIO,
        );
        this.drawBandMirror(
            ctx,
            high,
            "#0055e1",
            "#0055e1",
            config.SCALE_HIGH,
            N,
            w,
            H,
            config.HEIGHT_RATIO,
        );

        const core = new Float32Array(N);
        for (let i = 0; i < N; i++) {
            core[i] = Math.min(1, mid[i] * config.CORE_BOOST);
        }
        this.drawBandMirror(
            ctx,
            core,
            "rgba(255,255,255,0.8)",
            "rgba(255,255,255,0.0)",
            config.SCALE_CORE,
            N,
            w,
            H,
            config.HEIGHT_RATIO,
        );
    }

    drawSimpleWaveformMirror(ctx, data, W, H) {
        const N = Math.min(data.length, Math.floor(W));
        const w = W / N;
        const wave = new Float32Array(N);

        const samplesPerPixel = data.length / N;

        for (let i = 0; i < N; i++) {
            if (samplesPerPixel > 1) {
                const start = Math.floor(i * samplesPerPixel);
                const end = Math.min(
                    data.length,
                    Math.ceil((i + 1) * samplesPerPixel),
                );

                let max = 0;
                for (let j = start; j < end; j++) {
                    max = Math.max(max, (data[j].height || 0) / 255);
                }
                wave[i] = max;
            } else {
                wave[i] = (data[i]?.height || 0) / 255;
            }
        }

        this.drawBandMirror(
            ctx,
            wave,
            "#00d4ff",
            "#00d4ff",
            0.85,
            N,
            w,
            H,
            0.48,
        );
    }

    drawBandMirror(
        ctx,
        data,
        topColor,
        bottomColor,
        scale,
        N,
        w,
        H,
        heightRatio,
    ) {
        ctx.beginPath();
        ctx.imageSmoothingEnabled = false;

        for (let i = 0; i < N; i++) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = H / 2 - amp * (H * heightRatio * scale);

            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }

        for (let i = N - 1; i >= 0; i--) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = H / 2 + amp * (H * heightRatio * scale);
            ctx.lineTo(x, y);
        }

        ctx.closePath();

        const grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, topColor);
        grad.addColorStop(1, bottomColor);

        ctx.fillStyle = grad;
        ctx.lineJoin = "round";
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

        this.applyZoomToDeck("a");
        this.applyZoomToDeck("b");

        const sharedZoomEl = document.getElementById("sharedZoomLevel");
        if (sharedZoomEl) {
            sharedZoomEl.textContent = `${this.sharedZoomLevel}x`;
        }
    }

    applyZoomToDeck(deckId) {
        const deck = this.decks[deckId];

        if (!deck.duration || deck.duration <= 0) return;

        const currentTime = deck.audio.currentTime;
        const pitchMultiplier = 1 + deck.pitchValue / 100;
        const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
        const visibleDuration = deck.duration / effectiveZoom;
        const minOffset = -visibleDuration / 2;

        deck.waveformOffset = currentTime - visibleDuration / 2;
        deck.waveformOffset = Math.max(
            minOffset,
            Math.min(deck.waveformOffset, deck.duration - visibleDuration),
        );

        const centerTime = deck.waveformOffset + visibleDuration / 2;
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
        canvas.style.width = displayWidth + "px";
        canvas.style.height = displayHeight + "px";

        const ctx = canvas.getContext("2d", {
            alpha: false,
            desynchronized: true,
            willReadFrequently: false,
        });

        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(dpr, dpr);
        ctx.imageSmoothingEnabled = false;

        ctx.fillStyle = "#0b0b0b";
        ctx.fillRect(0, 0, displayWidth, displayHeight);

        if (!deck.waveformData) {
            ctx.fillStyle = "#333";
            ctx.font = "14px Arial";
            ctx.textAlign = "center";
            ctx.fillText(
                "No waveform data",
                displayWidth / 2,
                displayHeight / 2,
            );
            return;
        }

        const waveData =
            deck.waveformData.three_band_detail ||
            deck.waveformData.color_data ||
            deck.waveformData.preview_data;

        if (!waveData || waveData.length === 0) {
            ctx.fillStyle = "#333";
            ctx.font = "14px Arial";
            ctx.textAlign = "center";
            ctx.fillText(
                "No waveform data",
                displayWidth / 2,
                displayHeight / 2,
            );
            return;
        }

        const pitchMultiplier = 1 + deck.pitchValue / 100;
        const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;

        const viewStart = deck.waveformOffset;
        const viewDuration = deck.duration / effectiveZoom;
        const viewEnd = viewStart + viewDuration;

        const leadingBlankDuration = Math.max(0, -viewStart);
        const leadingBlankWidth =
            (leadingBlankDuration / viewDuration) * displayWidth;

        const actualStart = Math.max(0, viewStart);
        const startIndex = Math.floor(
            (actualStart / deck.duration) * waveData.length,
        );
        const endIndex = Math.ceil((viewEnd / deck.duration) * waveData.length);
        const visibleData = waveData.slice(startIndex, endIndex);

        if (visibleData.length === 0) return;

        const dataWidth = displayWidth - leadingBlankWidth;

        ctx.save();
        ctx.translate(leadingBlankWidth, 0);

        const is3Band = visibleData[0] && visibleData[0].mid !== undefined;

        if (is3Band) {
            this.draw3BandWaveformMirror(
                ctx,
                visibleData,
                dataWidth,
                displayHeight,
            );
        } else {
            this.drawSimpleWaveformMirror(
                ctx,
                visibleData,
                dataWidth,
                displayHeight,
            );
        }

        ctx.restore();

        this.renderBeatgrid(
            deckId,
            ctx,
            displayWidth,
            displayHeight,
            viewStart,
            viewDuration,
        );
    }

    renderBeatgrid(deckId, ctx, width, height, viewStart, viewDuration) {
        const deck = this.decks[deckId];

        if (!deck.track || !deck.track.bpm || deck.track.bpm <= 0) return;

        const pitchMultiplier = 1 + deck.pitchValue / 100;
        const viewEnd = viewStart + viewDuration;

        // Beatgrid config (matching Waveform.html)
        const DENSITY = 0.4; // Mengontrol jumlah gelombang
        const HEIGHT_RATIO = 0.48; // Mengontrol tinggi gelombang
        const CONE_CURVE = 1.8;
        const CONE_BOOST = 1.15;
        const DECAY = 4.5; // Sama dengan Waveform.html
        const FLAT_ZONE = 0;
        const TRANSIENT_ZONE = 0.0;

        // Amplitude envelope function
        function getAmplitude(progress) {
            // Flat zone (completely flat)
            if (progress <= FLAT_ZONE) return 0;

            const coneEnd = FLAT_ZONE + TRANSIENT_ZONE;

            // Cone zone (sharp attack from flat)
            if (progress <= coneEnd) {
                const t = (progress - FLAT_ZONE) / TRANSIENT_ZONE;
                const curve = Math.pow(t, CONE_CURVE);
                return curve * CONE_BOOST;
            }

            // Body decay
            return Math.exp(-progress * DECAY);
        }

        // Use PQTZ beat grid data if available, otherwise fallback to BPM calculation
        if (
            deck.beatgridData &&
            Array.isArray(deck.beatgridData) &&
            deck.beatgridData.length > 0
        ) {
            // Separate paths for bars (downbeat) and regular beats
            ctx.beginPath();
            const barPath = new Path2D();
            const beatPath = new Path2D();
            deck.beatgridData.forEach((beat) => {
                const beatTime = beat.time;
                const relativePosition = (beatTime - viewStart) / viewDuration;
                const x = Math.floor(relativePosition * width) + 0.5; // +0.5 for crisp pixels
                // beat.beat == 1 means downbeat (first beat of bar)
                if (beat.beat === 1) {
                    // Bar line (merah)
                    barPath.moveTo(x, 0);
                    barPath.lineTo(x, height);
                } else {
                    // Regular beat line (putih)
                    beatPath.moveTo(x, 0);
                    beatPath.lineTo(x, height);
                }
            });
            // Draw bar lines (merah)
            ctx.strokeStyle = "rgba(255, 0, 0, 1)";
            ctx.lineWidth = 4;
            ctx.stroke(barPath);
            // Draw beat lines (putih)
            ctx.strokeStyle = "rgba(255, 255, 255, 0.8)";
            ctx.lineWidth = 3;
            ctx.stroke(beatPath);
        } else {
            // Fallback: Use simple BPM calculation when no beat grid data available
            const effectiveBPM = deck.track.bpm * pitchMultiplier;
            const beatInterval = 60 / effectiveBPM;
            const barInterval = beatInterval * 4; // 1 bar = 4 beats
            // Separate paths for bars and beats
            const barPath = new Path2D();
            const beatPath = new Path2D();
            const firstBeat =
                Math.ceil(viewStart / beatInterval) * beatInterval;
            for (
                let beatTime = firstBeat;
                beatTime < viewEnd;
                beatTime += beatInterval
            ) {
                const relativePosition = (beatTime - viewStart) / viewDuration;
                const x = Math.floor(relativePosition * width) + 0.5;
                // Check if this is a bar (downbeat) - every 4 beats
                const beatNumber = Math.round(beatTime / beatInterval) % 4;
                if (beatNumber === 0) {
                    // Bar line (merah)
                    barPath.moveTo(x, 0);
                    barPath.lineTo(x, height);
                } else {
                    // Regular beat line (putih)
                    beatPath.moveTo(x, 0);
                    beatPath.lineTo(x, height);
                }
            }
            // Draw bar lines (merah)
            ctx.strokeStyle = "rgba(255, 0, 0, 0.5)";
            ctx.lineWidth = 2;
            ctx.stroke(barPath);
            // Draw beat lines (putih)
            ctx.strokeStyle = "rgba(255, 255, 255, 0.25)";
            ctx.lineWidth = 1;
            ctx.stroke(beatPath);
        }
    }

    renderCueMarkers(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const container = document.getElementById(`cueMarkers${deckLabel}`);

        if (!container || !deck.duration || deck.duration <= 0) return;

        container.innerHTML = "";

        const pitchMultiplier = 1 + deck.pitchValue / 100;
        const effectiveZoom = this.sharedZoomLevel * pitchMultiplier;
        const viewStart = deck.waveformOffset;
        const viewDuration = deck.duration / effectiveZoom;
        const viewEnd = viewStart + viewDuration;

        // hotCues is now 0-based (0=A, 1=B, 2=C, etc.)
        Object.entries(deck.hotCues).forEach(([cueIndex, cue]) => {
            if (cue.time >= viewStart && cue.time <= viewEnd) {
                const relativePosition = (cue.time - viewStart) / viewDuration;
                const marker = document.createElement("div");
                marker.className = "cue-marker";
                marker.style.left = `${relativePosition * 100}%`;
                const cueLabel = String.fromCharCode(65 + parseInt(cueIndex)); // 0=A, 1=B, etc.
                marker.title = `Cue ${cueLabel}: ${this.formatTime(cue.time)}`;
                marker.innerHTML = `<div class="cue-marker-label">${cueLabel}</div>`;
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
        deck.audio.src = "";
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
            const ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        for (let i = 1; i <= 8; i++) {
            const cueTimeEl = document.getElementById(
                `cueTime${deckLabel}${i}`,
            );
            if (cueTimeEl) {
                cueTimeEl.textContent = "--:--";
            }

            const cuePad = document.querySelector(
                `.hot-cue-pad[data-deck="${deckId}"][data-cue="${i}"]`,
            );
            if (cuePad) {
                cuePad.classList.remove("active");
            }
        }

        const cueMarkersEl = document.getElementById(`cueMarkers${deckLabel}`);
        if (cueMarkersEl) {
            cueMarkersEl.innerHTML = "";
        }
    }

    onTrackEnded(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();

        deck.isPlaying = false;
        const playIcon = document.getElementById(`playIcon${deckLabel}`);
        if (playIcon) {
            playIcon.className = "fas fa-play";
        }

        if (deck.animationFrame) {
            cancelAnimationFrame(deck.animationFrame);
        }
    }

    updateTrackInfo(deckId) {
        this.updatePlayhead(deckId);
    }

    formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return "00:00";
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
    }

    escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text || "";
        return div.innerHTML;
    }

    setPitch(deckId, pitchPercent, skipBPMSync = false) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();

        if (!deck.track) return;

        deck.pitchValue = parseFloat(pitchPercent);

        const playbackRate = 1 + deck.pitchValue / 100;
        deck.audio.playbackRate = playbackRate;
        deck.audio.preservesPitch = deck.masterTempo;

        const pitchValueEl = document.getElementById(`pitchValue${deckLabel}`);
        if (pitchValueEl) {
            const sign = deck.pitchValue >= 0 ? "+" : "";
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
            const otherDeckId = deckId === "a" ? "b" : "a";
            const otherDeck = this.decks[otherDeckId];

            if (
                otherDeck.track &&
                otherDeck.originalBPM &&
                otherDeck.bpmSyncEnabled
            ) {
                const targetBPM = deck.originalBPM * playbackRate;
                const requiredPitchPercent =
                    (targetBPM / otherDeck.originalBPM - 1) * 100;

                const otherDeckLabel = otherDeckId.toUpperCase();
                const otherSlider = document.getElementById(
                    `pitchSlider${otherDeckLabel}`,
                );
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
                btn.classList.add("active");
                btn.innerHTML = '<i class="fas fa-lock"></i>';
                btn.title = "Master Tempo ON (Key Locked)";
            } else {
                btn.classList.remove("active");
                btn.innerHTML = '<i class="fas fa-lock-open"></i>';
                btn.title = "Master Tempo OFF (Key Changes with Pitch)";
            }
        }
    }

    setMasterDeck(deckId) {
        const otherDeckId = deckId === "a" ? "b" : "a";

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
                btn.classList.add("active");
                btn.title = "Master Deck (Sync Source)";
            } else {
                btn.classList.remove("active");
                btn.title = "Set as Master Deck";
            }
        }
    }

    syncToMaster(deckId, mode = "bpm") {
        if (!this.masterDeck) {
            console.warn(
                "[Sync] No master deck selected. Please select a master deck first.",
            );
            this.showNotification(
                "Please select a master deck first by clicking the MASTER button on either deck.",
                "warning",
                3000,
            );
            return;
        }

        let sourceDeckId, targetDeckId;

        if (deckId === this.masterDeck) {
            sourceDeckId = this.masterDeck;
            targetDeckId = deckId === "a" ? "b" : "a";

            if (!this.decks[targetDeckId].track) {
                console.warn(
                    `[Sync] Cannot push sync - Deck ${targetDeckId.toUpperCase()} has no track loaded.`,
                );
                this.showNotification(
                    `Cannot sync: Deck ${targetDeckId.toUpperCase()} has no track loaded. Load a track first.`,
                    "warning",
                    3000,
                );
                return;
            }

            console.log(
                `[Sync] Pushing master ${sourceDeckId.toUpperCase()} settings to Deck ${targetDeckId.toUpperCase()}`,
            );
        } else {
            sourceDeckId = this.masterDeck;
            targetDeckId = deckId;

            console.log(
                `[Sync] Syncing Deck ${targetDeckId.toUpperCase()} TO master ${sourceDeckId.toUpperCase()}`,
            );
        }

        const snapBeats = mode === "beat";
        this.syncBPM(sourceDeckId, targetDeckId, snapBeats);
    }

    syncBPM(sourceDeckId, targetDeckId, snapBeats = false) {
        const sourceDeck = this.decks[sourceDeckId];
        const targetDeck = this.decks[targetDeckId];

        if (!sourceDeck.track || !targetDeck.track) {
            console.warn("Both decks must have tracks loaded to sync BPM");
            return;
        }

        if (!sourceDeck.originalBPM || !targetDeck.originalBPM) {
            console.warn("BPM information not available for sync");
            return;
        }

        const targetBPM =
            sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
        const requiredPitchPercent =
            (targetBPM / targetDeck.originalBPM - 1) * 100;

        const targetDeckLabel = targetDeckId.toUpperCase();
        const slider = document.getElementById(`pitchSlider${targetDeckLabel}`);
        if (slider) {
            slider.value = requiredPitchPercent.toFixed(1);
            this.setPitch(targetDeckId, requiredPitchPercent);
        }

        if (snapBeats) {
            this.snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM);
        }

        console.log(
            `Synced ${targetDeckId.toUpperCase()} (${targetDeck.originalBPM} BPM) to ${sourceDeckId.toUpperCase()} (${targetBPM.toFixed(2)} BPM)${snapBeats ? " + Beat Grid" : ""}`,
        );
    }

    snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM) {
        const sourceDeck = this.decks[sourceDeckId];
        const targetDeck = this.decks[targetDeckId];

        if (
            !sourceDeck.beatgridData ||
            !Array.isArray(sourceDeck.beatgridData) ||
            sourceDeck.beatgridData.length === 0
        ) {
            console.warn(
                `[Beat Sync] No beat grid data available for source deck ${sourceDeckId.toUpperCase()}. Beat sync requires analyzed beat grids.`,
            );
            this.showNotification(
                `Beat grid not available for ${sourceDeckId.toUpperCase()}. Beat sync requires tracks analyzed in Rekordbox.`,
                "error",
                4000,
            );
            return;
        }

        if (
            !targetDeck.beatgridData ||
            !Array.isArray(targetDeck.beatgridData) ||
            targetDeck.beatgridData.length === 0
        ) {
            console.warn(
                `[Beat Sync] No beat grid data available for target deck ${targetDeckId.toUpperCase()}. Beat sync requires analyzed beat grids.`,
            );
            this.showNotification(
                `Beat grid not available for ${targetDeckId.toUpperCase()}. Beat sync requires tracks analyzed in Rekordbox.`,
                "error",
                4000,
            );
            return;
        }

        const sourceBPM =
            sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
        const targetBeatLength = 60 / targetBPM;
        const sourceBeatLength = 60 / sourceBPM;

        const sourceFirstBeatOffset = sourceDeck.beatgridData[0].time || 0;
        const targetFirstBeatOffset = targetDeck.beatgridData[0].time || 0;

        const sourceCenterPoint = sourceDeck.audio.currentTime;
        const targetCenterPoint = targetDeck.audio.currentTime;

        const sourceTimeFromFirstBeat = sourceCenterPoint - sourceFirstBeatOffset;
        const targetTimeFromFirstBeat = targetCenterPoint - targetFirstBeatOffset;

        const sourceNearestBeatNumber = Math.round(sourceTimeFromFirstBeat / sourceBeatLength);
        const targetNearestBeatNumber = Math.round(targetTimeFromFirstBeat / targetBeatLength);

        const sourceNearestBeatTime = sourceFirstBeatOffset + (sourceNearestBeatNumber * sourceBeatLength);
        const targetNearestBeatTime = targetFirstBeatOffset + (targetNearestBeatNumber * targetBeatLength);

        const sourceBeatOffsetFromCenter = sourceNearestBeatTime - sourceCenterPoint;
        const targetBeatOffsetFromCenter = targetNearestBeatTime - targetCenterPoint;

        const offsetDifference = sourceBeatOffsetFromCenter - targetBeatOffsetFromCenter;

        let newTargetTime = targetCenterPoint + offsetDifference;

        newTargetTime = Math.max(
            0,
            Math.min(newTargetTime, targetDeck.duration - 0.1),
        );

        if (Math.abs(offsetDifference) > 0.001) {
            targetDeck.audio.currentTime = newTargetTime;
            this.updatePlayhead(targetDeckId);
        }

        const adjustmentMs = Math.round(offsetDifference * 1000);
        const sourceBeatOffsetMs = Math.round(sourceBeatOffsetFromCenter * 1000);
        const targetBeatOffsetMs = Math.round(targetBeatOffsetFromCenter * 1000);

        console.log(
            `[Beat Sync - Grid Center Alignment]\n` +
            `  Master ${sourceDeckId.toUpperCase()}: Center=${sourceCenterPoint.toFixed(3)}s | Nearest Beat=${sourceNearestBeatTime.toFixed(3)}s | Offset=${sourceBeatOffsetMs}ms\n` +
            `  Target ${targetDeckId.toUpperCase()}: Center=${targetCenterPoint.toFixed(3)}s | Nearest Beat=${targetNearestBeatTime.toFixed(3)}s (before) | Offset=${targetBeatOffsetMs}ms\n` +
            `  → Adjustment: ${adjustmentMs}ms | New Target Time: ${newTargetTime.toFixed(3)}s\n` +
            `  ✓ Beats aligned at center point | Phase locked`
        );

        this.showNotification(
            `Beat Sync: ${Math.abs(adjustmentMs)}ms adjustment | Beats aligned to center`,
            "success",
            2000,
        );
    }

    updateBPMDisplay(deckId, currentBPM) {
        const deckLabel = deckId.toUpperCase();
        const trackInfo = document.getElementById(`trackInfo${deckLabel}`);

        if (trackInfo) {
            const bpmDisplay = trackInfo.querySelector(".bpm-display");
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
        const playbackRate = 1 + newPitch / 100;
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

        const playbackRate = 1 + deck.pitchValue / 100;
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

        const volumeValueEl = document.getElementById(
            `volumeValue${deckLabel}`,
        );
        if (volumeValueEl) {
            volumeValueEl.textContent = `${Math.round(deck.volume)}%`;
        }
    }

    toggleBPMSync(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();
        const otherDeckId = deckId === "a" ? "b" : "a";
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
                this.showNotification(
                    `Master deck auto-set to ${deckLabel}`,
                    "info",
                    2000,
                );
                console.log(`[BPM Sync] Auto-set master deck to ${deckLabel}`);
            }
        }

        // Update button UI
        const btn = document.getElementById(`bpmSync${deckLabel}`);
        if (btn) {
            if (deck.bpmSyncEnabled) {
                btn.classList.add("active");
            } else {
                btn.classList.remove("active");
            }
        }

        // If enabling sync, sync BPM now
        if (deck.bpmSyncEnabled && this.masterDeck) {
            this.syncToMaster(deckId, "bpm");
        }

        console.log(
            `BPM Sync ${deck.bpmSyncEnabled ? "ON" : "OFF"} for Deck ${deckLabel}`,
        );
    }

    toggleQuantize(deckId) {
        const deck = this.decks[deckId];
        const deckLabel = deckId.toUpperCase();

        deck.quantizeEnabled = !deck.quantizeEnabled;

        const btn = document.getElementById(`quantize${deckLabel}`);
        if (btn) {
            if (deck.quantizeEnabled) {
                btn.classList.add("active");
            } else {
                btn.classList.remove("active");
            }
        }

        console.log(
            `Quantize ${deck.quantizeEnabled ? "ON" : "OFF"} for Deck ${deckLabel}`,
        );
    }

    quantizeTime(deckId, targetTime) {
        const deck = this.decks[deckId];

        if (!deck.quantizeEnabled || !deck.track || !deck.originalBPM) {
            return targetTime;
        }

        const currentBPM = deck.originalBPM * (1 + deck.pitchValue / 100);
        const beatLength = 60 / currentBPM;

        let firstBeatOffset = 0;
        if (
            deck.beatgridData &&
            Array.isArray(deck.beatgridData) &&
            deck.beatgridData.length > 0
        ) {
            firstBeatOffset = deck.beatgridData[0].time;
        }

        const timeFromFirstBeat = targetTime - firstBeatOffset;
        const beatNumber = Math.round(timeFromFirstBeat / beatLength);
        const quantizedTime = firstBeatOffset + beatNumber * beatLength;

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
