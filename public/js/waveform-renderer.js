class WaveformRenderer {
    constructor(overviewCanvasId, detailedCanvasId) {
        this.overviewCanvas = document.getElementById(overviewCanvasId);
        this.detailedCanvas = document.getElementById(detailedCanvasId);
        this.waveformData = null;
        this.duration = 0;
        this.playheadPosition = 0;
        this.detailedZoom = 10;
        this.detailedScrollOffset = 0;
        this.onClickCallback = null;
        
        // Configuration for efficient rendering
        this.config = {
            HEIGHT_RATIO: 0.48,
            SCALE_LOW: 1.0,
            SCALE_MID: 0.85,
            SCALE_HIGH: 0.7,
            SCALE_CORE: 0.3,
            CORE_BOOST: 1.7
        };
        
        this.setupCanvases();
    }
    
    setupCanvases() {
        if (this.overviewCanvas) {
            // Wait for DOM to be ready and canvas to have size
            const width = this.overviewCanvas.offsetWidth || this.overviewCanvas.parentElement?.offsetWidth || 800;
            this.overviewCanvas.width = width * 2;
            this.overviewCanvas.height = 120;
            
            this.overviewCanvas.addEventListener('click', (e) => {
                this.handleOverviewClick(e);
            });
        }
        
        if (this.detailedCanvas) {
            const width = this.detailedCanvas.offsetWidth || this.detailedCanvas.parentElement?.offsetWidth || 800;
            this.detailedCanvas.width = width * 2;
            this.detailedCanvas.height = 240;
            
            this.detailedCanvas.addEventListener('click', (e) => {
                this.handleDetailedClick(e);
            });
        }
    }
    
    loadWaveform(waveformData, duration) {
        this.waveformData = waveformData;
        this.duration = duration;
        this.detailedScrollOffset = 0;
        this.playheadPosition = 0;
        
        if (this.overviewCanvas && this.overviewCanvas.width === 0) {
            this.setupCanvases();
        }
        
        this.renderOverview();
        this.renderDetailed();
    }
    
    renderOverview() {
        if (!this.overviewCanvas || !this.waveformData) {
            return;
        }
        
        const ctx = this.overviewCanvas.getContext('2d', { 
            alpha: false,
            desynchronized: true
        });
        const width = this.overviewCanvas.width;
        const height = this.overviewCanvas.height;
        
        ctx.fillStyle = '#0a0a0a';
        ctx.fillRect(0, 0, width, height);
        
        const waveData = this.waveformData.three_band_preview || this.waveformData.color_data || this.waveformData.preview_data;
        if (!waveData || waveData.length === 0) {
            ctx.fillStyle = '#333';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No waveform data available', width / 2, height / 2);
            return;
        }
        
        const is3Band = waveData[0].mid !== undefined;
        
        if (is3Band) {
            // Efficient path-based rendering for 3-band
            this.renderWaveform3Band(ctx, waveData, width, height);
        } else {
            // Simple waveform rendering
            this.renderWaveformSimple(ctx, waveData, width, height);
        }
        
        ctx.strokeStyle = '#00d4ff30';
        ctx.lineWidth = 1;
        ctx.strokeRect(0, 0, width, height);
        
        this.drawOverviewPlayhead();
    }
    
    renderDetailed() {
        if (!this.detailedCanvas || !this.waveformData) return;
        
        const ctx = this.detailedCanvas.getContext('2d', { 
            alpha: false,
            desynchronized: true
        });
        const width = this.detailedCanvas.width;
        const height = this.detailedCanvas.height;
        
        ctx.fillStyle = '#0a0a0a';
        ctx.fillRect(0, 0, width, height);
        
        const waveData = this.waveformData.three_band_detail || this.waveformData.color_data || this.waveformData.preview_data;
        if (!waveData || waveData.length === 0) {
            ctx.fillStyle = '#333';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No waveform data available', width / 2, height / 2);
            return;
        }
        
        const visibleDuration = this.duration / this.detailedZoom;
        const startTime = this.detailedScrollOffset;
        const endTime = startTime + visibleDuration;
        const startIndex = Math.floor((startTime / this.duration) * waveData.length);
        const endIndex = Math.ceil((endTime / this.duration) * waveData.length);
        const visibleData = waveData.slice(startIndex, endIndex);
        
        if (visibleData.length === 0) return;
        
        const is3Band = visibleData[0] && visibleData[0].mid !== undefined;
        
        if (is3Band) {
            // Efficient path-based rendering for 3-band
            this.renderWaveform3Band(ctx, visibleData, width, height);
        } else {
            // Simple waveform rendering
            this.renderWaveformSimple(ctx, visibleData, width, height);
        }
        
        ctx.strokeStyle = '#00d4ff30';
        ctx.lineWidth = 1;
        ctx.strokeRect(0, 0, width, height);
        
        this.drawDetailedPlayhead();
    }
    
    drawOverviewPlayhead() {
        if (!this.overviewCanvas || !this.duration) return;
        
        const ctx = this.overviewCanvas.getContext('2d');
        const width = this.overviewCanvas.width;
        const height = this.overviewCanvas.height;
        
        const x = (this.playheadPosition / this.duration) * width;
        
        ctx.shadowBlur = 8;
        ctx.shadowColor = '#FF4444';
        ctx.strokeStyle = '#FF4444';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, height);
        ctx.stroke();
        ctx.shadowBlur = 0;
        
        ctx.fillStyle = '#FF4444';
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x - 6, 10);
        ctx.lineTo(x + 6, 10);
        ctx.closePath();
        ctx.fill();
    }
    
    drawDetailedPlayhead() {
        if (!this.detailedCanvas || !this.duration) return;
        
        const ctx = this.detailedCanvas.getContext('2d');
        const width = this.detailedCanvas.width;
        const height = this.detailedCanvas.height;
        
        const visibleDuration = this.duration / this.detailedZoom;
        const relativePosition = this.playheadPosition - this.detailedScrollOffset;
        
        if (relativePosition >= 0 && relativePosition <= visibleDuration) {
            const x = (relativePosition / visibleDuration) * width;
            
            ctx.shadowBlur = 10;
            ctx.shadowColor = '#FF4444';
            ctx.strokeStyle = '#FF4444';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
            ctx.shadowBlur = 0;
            
            ctx.fillStyle = '#FF4444';
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x - 6, 10);
            ctx.lineTo(x + 6, 10);
            ctx.closePath();
            ctx.fill();
        }
    }
    
    updatePlayhead(currentTime) {
        this.playheadPosition = currentTime;
        
        const visibleDuration = this.duration / this.detailedZoom;
        const centerPosition = this.detailedScrollOffset + (visibleDuration / 2);
        
        if (Math.abs(this.playheadPosition - centerPosition) > visibleDuration * 0.3) {
            this.detailedScrollOffset = Math.max(0, this.playheadPosition - (visibleDuration / 2));
            this.detailedScrollOffset = Math.min(this.detailedScrollOffset, this.duration - visibleDuration);
        }
        
        this.renderOverview();
        this.renderDetailed();
    }
    
    // Efficient 3-band waveform rendering with path-based approach
    renderWaveform3Band(ctx, waveData, width, height) {
        const N = waveData.length;
        const w = width / N;
        
        // Prepare normalized data
        const lowData = [], midData = [], highData = [];
        for (let i = 0; i < N; i++) {
            lowData[i] = (waveData[i].low || 0) / 255;
            midData[i] = (waveData[i].mid || 0) / 255;
            highData[i] = (waveData[i].high || 0) / 255;
        }
        
        // Render each band as a single path (much more efficient!)
        this.drawBandMirror(ctx, lowData, '#ffffff', '#ffffff', this.config.SCALE_LOW, width, height, N);
        this.drawBandMirror(ctx, midData, '#ffa600', '#ffa600', this.config.SCALE_MID, width, height, N);
        this.drawBandMirror(ctx, highData, '#0055e1', '#0055e1', this.config.SCALE_HIGH, width, height, N);
        
        // Core white center boost
        const coreData = midData.map(v => Math.min(1, v * this.config.CORE_BOOST));
        this.drawBandMirror(ctx, coreData, 'rgba(255,255,255,0.8)', 'rgba(255,255,255,0.0)', this.config.SCALE_CORE, width, height, N);
    }
    
    // Simple waveform rendering
    renderWaveformSimple(ctx, waveData, width, height) {
        const N = waveData.length;
        const data = waveData.map(s => (s.height || 0) / 255);
        this.drawBandMirror(ctx, data, '#00d4ff', '#00d4ff', 0.85, width, height, N);
    }
    
    // Draw a band with mirror (top and bottom) in one efficient path
    drawBandMirror(ctx, data, topColor, bottomColor, scale, width, height, N) {
        const w = width / N;
        const centerY = height / 2;
        const heightRatio = this.config.HEIGHT_RATIO;
        
        ctx.beginPath();
        ctx.imageSmoothingEnabled = false;
        
        // Draw top half
        for (let i = 0; i < N; i++) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = centerY - amp * (height * heightRatio * scale);
            
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        
        // Draw bottom half (mirrored)
        for (let i = N - 1; i >= 0; i--) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = centerY + amp * (height * heightRatio * scale);
            ctx.lineTo(x, y);
        }
        
        ctx.closePath();
        
        // Apply gradient
        const grad = ctx.createLinearGradient(0, 0, 0, height);
        grad.addColorStop(0, topColor);
        grad.addColorStop(1, bottomColor);
        
        ctx.fillStyle = grad;
        ctx.lineJoin = 'round';
        ctx.fill();
    }
    
    handleOverviewClick(event) {
        if (!this.duration) return;
        
        const rect = this.overviewCanvas.getBoundingClientRect();
        const x = (event.clientX - rect.left) * 2;
        const percent = x / this.overviewCanvas.width;
        const time = percent * this.duration;
        
        if (this.onClickCallback) {
            this.onClickCallback(time);
        }
    }
    
    handleDetailedClick(event) {
        if (!this.duration) return;
        
        const rect = this.detailedCanvas.getBoundingClientRect();
        const x = (event.clientX - rect.left) * 2;
        const visibleDuration = this.duration / this.detailedZoom;
        const percent = x / this.detailedCanvas.width;
        const time = this.detailedScrollOffset + (percent * visibleDuration);
        
        if (this.onClickCallback) {
            this.onClickCallback(time);
        }
    }
    
    onClick(callback) {
        this.onClickCallback = callback;
    }
    
    clear() {
        if (this.overviewCanvas) {
            const ctx = this.overviewCanvas.getContext('2d');
            ctx.fillStyle = '#1a1a1a';
            ctx.fillRect(0, 0, this.overviewCanvas.width, this.overviewCanvas.height);
        }
        
        if (this.detailedCanvas) {
            const ctx = this.detailedCanvas.getContext('2d');
            ctx.fillStyle = '#1a1a1a';
            ctx.fillRect(0, 0, this.detailedCanvas.width, this.detailedCanvas.height);
        }
    }
}
