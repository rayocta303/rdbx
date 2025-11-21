class WaveformRenderer {
    static instances = new Set();
    static resizeScheduled = false;

    static onResize() {
        if (WaveformRenderer.resizeScheduled) return;
        WaveformRenderer.resizeScheduled = true;

        requestAnimationFrame(() => {
            WaveformRenderer.instances.forEach((instance) =>
                instance.handleResize(),
            );
            WaveformRenderer.resizeScheduled = false;
        });
    }

    constructor(overviewCanvasId, detailedCanvasId, customConfig = {}) {
        this.overviewCanvas = document.getElementById(overviewCanvasId);
        this.detailedCanvas = document.getElementById(detailedCanvasId);
        this.waveformData = null;
        this.duration = 0;
        this.playheadPosition = 0;
        this.detailedZoom = 10;
        this.detailedScrollOffset = 0;
        this.onClickCallback = null;

        this.DPR = window.devicePixelRatio || 1;

        // Konfigurasi waveform yang dapat disesuaikan
        this.config = {
            // Waveform rendering
            HEIGHT_RATIO: 0.48,
            SCALE_LOW: 1.0,
            SCALE_MID: 0.85,
            SCALE_HIGH: 0.7,
            SCALE_CORE: 0.3,
            CORE_BOOST: 1.7,

            // Beat-related (jika diperlukan untuk fitur masa depan)
            DENSITY: 0.4,
            DECAY: 4.5,
            NOISE: 0,
            FLAT_ZONE: 0,
            TRANSIENT_ZONE: 0.0,
            CONE_CURVE: 2.8,
            CONE_BOOST: 1.15,
        };

        this.initCanvases();

        WaveformRenderer.instances.add(this);
        if (WaveformRenderer.instances.size === 1) {
            window.addEventListener("resize", WaveformRenderer.onResize);
        }
    }

    initCanvases() {
        if (this.overviewCanvas) {
            this.setupCanvas(this.overviewCanvas, 120);
            this.overviewCanvas.addEventListener("click", (e) =>
                this.handleOverviewClick(e),
            );
        }

        if (this.detailedCanvas) {
            this.setupCanvas(this.detailedCanvas, 240);
            this.detailedCanvas.addEventListener("click", (e) =>
                this.handleDetailedClick(e),
            );
        }
    }

    setupCanvas(canvas, height) {
        const W =
            canvas.clientWidth || canvas.parentElement?.clientWidth || 800;
        const H = height;

        canvas.width = W * this.DPR;
        canvas.height = H * this.DPR;
        canvas.style.width = W + "px";
        canvas.style.height = H + "px";

        const ctx = canvas.getContext("2d", { 
            alpha: false,
            desynchronized: true,
            willReadFrequently: false
        });
        ctx.setTransform(this.DPR, 0, 0, this.DPR, 0, 0);
    }

    handleResize() {
        if (this.overviewCanvas) this.setupCanvas(this.overviewCanvas, 120);
        if (this.detailedCanvas) this.setupCanvas(this.detailedCanvas, 240);

        if (this.waveformData) {
            this.renderOverview();
            this.renderDetailed();
        }
    }

    loadWaveform(waveformData, duration) {
        this.waveformData = waveformData;
        this.duration = duration;
        this.detailedScrollOffset = 0;
        this.playheadPosition = 0;

        this.renderOverview();
        this.renderDetailed();
    }

    renderOverview() {
        if (!this.overviewCanvas || !this.waveformData) return;

        const ctx = this.overviewCanvas.getContext("2d", {
            alpha: false,
            desynchronized: true,
            willReadFrequently: false
        });
        const W = this.overviewCanvas.width / this.DPR;
        const H = this.overviewCanvas.height / this.DPR;

        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = "#0b0b0b";
        ctx.fillRect(0, 0, W, H);

        const waveData =
            this.waveformData.three_band_preview ||
            this.waveformData.color_data ||
            this.waveformData.preview_data;
        if (!waveData || waveData.length === 0) {
            ctx.fillStyle = "#333";
            ctx.font = "14px Arial";
            ctx.textAlign = "center";
            ctx.fillText("No waveform data", W / 2, H / 2);
            return;
        }

        const is3Band = waveData[0]?.mid !== undefined;

        if (is3Band) {
            this.draw3BandWaveform(ctx, waveData, W, H);
        } else {
            this.drawSimpleWaveform(ctx, waveData, W, H);
        }

        if (this.duration > 0) {
            this.drawPlayhead(ctx, W, H, this.playheadPosition / this.duration);
        }
    }

    renderDetailed() {
        if (!this.detailedCanvas || !this.waveformData) return;

        const ctx = this.detailedCanvas.getContext("2d", {
            alpha: false,
            desynchronized: true,
            willReadFrequently: false
        });
        const W = this.detailedCanvas.width / this.DPR;
        const H = this.detailedCanvas.height / this.DPR;

        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = "#0b0b0b";
        ctx.fillRect(0, 0, W, H);

        const waveData =
            this.waveformData.three_band_detail ||
            this.waveformData.color_data ||
            this.waveformData.preview_data;
        if (!waveData || waveData.length === 0) {
            ctx.fillStyle = "#333";
            ctx.font = "16px Arial";
            ctx.textAlign = "center";
            ctx.fillText("No waveform data", W / 2, H / 2);
            return;
        }

        const visibleDuration = this.duration / this.detailedZoom;
        const startTime = this.detailedScrollOffset;
        const endTime = startTime + visibleDuration;
        const startIndex = Math.floor(
            (startTime / this.duration) * waveData.length,
        );
        const endIndex = Math.ceil((endTime / this.duration) * waveData.length);
        const visibleData = waveData.slice(startIndex, endIndex);

        if (visibleData.length === 0) return;

        const is3Band = visibleData[0]?.mid !== undefined;

        if (is3Band) {
            this.draw3BandWaveform(ctx, visibleData, W, H);
        } else {
            this.drawSimpleWaveform(ctx, visibleData, W, H);
        }

        if (this.duration > 0) {
            const relativePosition =
                this.playheadPosition - this.detailedScrollOffset;
            if (relativePosition >= 0 && relativePosition <= visibleDuration) {
                this.drawPlayhead(
                    ctx,
                    W,
                    H,
                    relativePosition / visibleDuration,
                );
            }
        }
    }

    draw3BandWaveform(ctx, data, W, H) {
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

        this.drawBandMirror(
            ctx,
            low,
            "#ffffff",
            "#ffffff",
            this.config.SCALE_LOW,
            N,
            w,
            H,
        );
        this.drawBandMirror(
            ctx,
            mid,
            "#ffa600",
            "#ffa600",
            this.config.SCALE_MID,
            N,
            w,
            H,
        );
        this.drawBandMirror(
            ctx,
            high,
            "#0055e1",
            "#0055e1",
            this.config.SCALE_HIGH,
            N,
            w,
            H,
        );

        const core = new Float32Array(N);
        for (let i = 0; i < N; i++) {
            core[i] = Math.min(1, mid[i] * this.config.CORE_BOOST);
        }
        this.drawBandMirror(
            ctx,
            core,
            "rgba(255,255,255,0.8)",
            "rgba(255,255,255,0.0)",
            this.config.SCALE_CORE,
            N,
            w,
            H,
        );
    }

    drawSimpleWaveform(ctx, data, W, H) {
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

        this.drawBandMirror(ctx, wave, "#00d4ff", "#00d4ff", 0.85, N, w, H);
    }

    drawBandMirror(ctx, data, topColor, bottomColor, scale, N, w, H) {
        ctx.save();
        ctx.beginPath();
        ctx.imageSmoothingEnabled = false;

        // Draw top half
        for (let i = 0; i < N; i++) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = H / 2 - amp * (H * this.config.HEIGHT_RATIO * scale);

            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }

        // Draw bottom half (mirror)
        for (let i = N - 1; i >= 0; i--) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = H / 2 + amp * (H * this.config.HEIGHT_RATIO * scale);
            ctx.lineTo(x, y);
        }

        ctx.closePath();

        // Create gradient
        const grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, topColor);
        grad.addColorStop(1, bottomColor);

        ctx.fillStyle = grad;
        ctx.lineJoin = "round";
        ctx.fill();

        ctx.restore();
    }

    drawPlayhead(ctx, W, H, position) {
        const x = position * W;

        ctx.save();
        ctx.shadowBlur = 8;
        ctx.shadowColor = "#FF4444";
        ctx.strokeStyle = "#FF4444";
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, H);
        ctx.stroke();
        ctx.restore();

        ctx.fillStyle = "#FF4444";
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x - 5, 8);
        ctx.lineTo(x + 5, 8);
        ctx.closePath();
        ctx.fill();
    }

    updatePlayhead(currentTime) {
        this.playheadPosition = currentTime;

        const visibleDuration = this.duration / this.detailedZoom;
        const centerPosition = this.detailedScrollOffset + visibleDuration / 2;

        if (
            Math.abs(this.playheadPosition - centerPosition) >
            visibleDuration * 0.3
        ) {
            this.detailedScrollOffset = Math.max(
                0,
                this.playheadPosition - visibleDuration / 2,
            );
            this.detailedScrollOffset = Math.min(
                this.detailedScrollOffset,
                this.duration - visibleDuration,
            );
        }

        this.renderOverview();
        this.renderDetailed();
    }

    handleOverviewClick(event) {
        if (!this.duration) return;

        const rect = this.overviewCanvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const percent = x / rect.width;
        const time = percent * this.duration;

        if (this.onClickCallback) {
            this.onClickCallback(time);
        }
    }

    handleDetailedClick(event) {
        if (!this.duration) return;

        const rect = this.detailedCanvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const visibleDuration = this.duration / this.detailedZoom;
        const percent = x / rect.width;
        const time = this.detailedScrollOffset + percent * visibleDuration;

        if (this.onClickCallback) {
            this.onClickCallback(time);
        }
    }

    onClick(callback) {
        this.onClickCallback = callback;
    }

    clear() {
        if (this.overviewCanvas) {
            const ctx = this.overviewCanvas.getContext("2d");
            const W = this.overviewCanvas.width / this.DPR;
            const H = this.overviewCanvas.height / this.DPR;
            ctx.clearRect(0, 0, W, H);
            ctx.fillStyle = "#0b0b0b";
            ctx.fillRect(0, 0, W, H);
        }

        if (this.detailedCanvas) {
            const ctx = this.detailedCanvas.getContext("2d");
            const W = this.detailedCanvas.width / this.DPR;
            const H = this.detailedCanvas.height / this.DPR;
            ctx.clearRect(0, 0, W, H);
            ctx.fillStyle = "#0b0b0b";
            ctx.fillRect(0, 0, W, H);
        }
    }

    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };

        // Re-render jika ada waveform data
        if (this.waveformData) {
            this.renderOverview();
            this.renderDetailed();
        }
    }

    getConfig() {
        return { ...this.config };
    }

    destroy() {
        WaveformRenderer.instances.delete(this);
        if (WaveformRenderer.instances.size === 0) {
            window.removeEventListener("resize", WaveformRenderer.onResize);
        }
    }
}