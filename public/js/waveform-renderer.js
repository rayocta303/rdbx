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
        const samplesPerPixel = waveData.length / width;
        let N = Math.min(waveData.length, width);
        
        // Pre-allocate typed arrays for better memory efficiency
        const lowData = new Float32Array(N);
        const midData = new Float32Array(N);
        const highData = new Float32Array(N);
        
        // Downsample efficiently: aggregate max values per pixel
        if (samplesPerPixel > 1) {
            for (let x = 0; x < N; x++) {
                const sampleStart = Math.floor(x * samplesPerPixel);
                const sampleEnd = Math.min(waveData.length, Math.ceil((x + 1) * samplesPerPixel));
                
                let maxLow = 0, maxMid = 0, maxHigh = 0;
                for (let i = sampleStart; i < sampleEnd; i++) {
                    const sample = waveData[i];
                    // Data is already normalized 0-1 from backend
                    maxLow = Math.max(maxLow, sample.low || 0);
                    maxMid = Math.max(maxMid, sample.mid || 0);
                    maxHigh = Math.max(maxHigh, sample.high || 0);
                }
                
                // Clamp to ensure valid range
                lowData[x] = Math.min(1, Math.max(0, maxLow));
                midData[x] = Math.min(1, Math.max(0, maxMid));
                highData[x] = Math.min(1, Math.max(0, maxHigh));
            }
        } else {
            // Direct copy when we have fewer samples than pixels
            for (let i = 0; i < N; i++) {
                // Data is already normalized 0-1 from backend
                lowData[i] = Math.min(1, Math.max(0, waveData[i].low || 0));
                midData[i] = Math.min(1, Math.max(0, waveData[i].mid || 0));
                highData[i] = Math.min(1, Math.max(0, waveData[i].high || 0));
            }
        }
        
        // Render each band as a single path (MUCH more efficient than per-bar drawing!)
        // Draw from bottom to top for proper layering
        this.drawBandMirror(ctx, lowData, '#ffffff', '#ffffff', this.config.SCALE_LOW, width, height);
        this.drawBandMirror(ctx, midData, '#ffa600', '#ffa600', this.config.SCALE_MID, width, height);
        this.drawBandMirror(ctx, highData, '#0055e1', '#0055e1', this.config.SCALE_HIGH, width, height);
        
        // Core white center boost for visual pop
        const coreData = new Float32Array(N);
        for (let i = 0; i < N; i++) {
            coreData[i] = Math.min(1, midData[i] * this.config.CORE_BOOST);
        }
        this.drawBandMirror(ctx, coreData, 'rgba(255,255,255,0.8)', 'rgba(255,255,255,0.0)', this.config.SCALE_CORE, width, height);
    }
    
    // Simple waveform rendering
    renderWaveformSimple(ctx, waveData, width, height) {
        const samplesPerPixel = waveData.length / width;
        let N = Math.min(waveData.length, width);
        const data = new Float32Array(N);
        
        // Downsample efficiently
        if (samplesPerPixel > 1) {
            for (let x = 0; x < N; x++) {
                const sampleStart = Math.floor(x * samplesPerPixel);
                const sampleEnd = Math.min(waveData.length, Math.ceil((x + 1) * samplesPerPixel));
                
                let maxHeight = 0;
                for (let i = sampleStart; i < sampleEnd; i++) {
                    // Data is already normalized 0-1 from backend
                    maxHeight = Math.max(maxHeight, waveData[i].height || 0);
                }
                data[x] = Math.min(1, Math.max(0, maxHeight));
            }
        } else {
            for (let i = 0; i < N; i++) {
                // Data is already normalized 0-1 from backend
                data[i] = Math.min(1, Math.max(0, waveData[i].height || 0));
            }
        }
        
        this.drawBandMirror(ctx, data, '#00d4ff', '#00d4ff', 0.85, width, height);
    }
    
    // Draw a band with mirror (top and bottom) in one efficient path
    // This is THE key optimization: instead of drawing thousands of individual bars,
    // we draw ONE path that creates the entire waveform shape
    drawBandMirror(ctx, data, topColor, bottomColor, scale, canvasWidth, canvasHeight) {
        const N = data.length;
        const w = canvasWidth / N;
        const centerY = canvasHeight / 2;
        const heightRatio = this.config.HEIGHT_RATIO;
        
        // Start a single path for the entire waveform
        ctx.beginPath();
        ctx.imageSmoothingEnabled = false;
        
        // Draw top contour (left to right)
        for (let i = 0; i < N; i++) {
            const x = Math.floor(i * w) + 0.5;  // +0.5 for crisp pixels
            const amp = data[i];
            const y = centerY - amp * (canvasHeight * heightRatio * scale);
            
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        
        // Draw bottom contour (right to left, mirrored)
        for (let i = N - 1; i >= 0; i--) {
            const x = Math.floor(i * w) + 0.5;
            const amp = data[i];
            const y = centerY + amp * (canvasHeight * heightRatio * scale);
            ctx.lineTo(x, y);
        }
        
        ctx.closePath();
        
        // Create gradient fill
        const grad = ctx.createLinearGradient(0, 0, 0, canvasHeight);
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
