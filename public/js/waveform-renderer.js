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
        
        this.setupCanvases();
    }
    
    setupCanvases() {
        if (this.overviewCanvas) {
            // Wait for DOM to be ready and canvas to have size
            const width = this.overviewCanvas.offsetWidth || this.overviewCanvas.parentElement?.offsetWidth || 800;
            this.overviewCanvas.width = width * 2;
            this.overviewCanvas.height = 60;
            
            this.overviewCanvas.addEventListener('click', (e) => {
                this.handleOverviewClick(e);
            });
        }
        
        if (this.detailedCanvas) {
            const width = this.detailedCanvas.offsetWidth || this.detailedCanvas.parentElement?.offsetWidth || 800;
            this.detailedCanvas.width = width * 2;
            this.detailedCanvas.height = 120;
            
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
        
        const waveData = this.waveformData.preview_data || this.waveformData.color_data;
        if (!waveData || waveData.length === 0) {
            ctx.fillStyle = '#333';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No waveform data available', width / 2, height / 2);
            return;
        }
        
        const isColorWaveform = waveData[0].r !== undefined;
        const samplesPerPixel = waveData.length / width;
        
        if (samplesPerPixel > 1) {
            for (let x = 0; x < width; x++) {
                const sampleStart = Math.floor(x * samplesPerPixel);
                const sampleEnd = Math.min(waveData.length, Math.ceil((x + 1) * samplesPerPixel));
                
                let maxHeight = 0;
                let maxR = 0, maxG = 0, maxB = 0;
                
                for (let i = sampleStart; i < sampleEnd; i++) {
                    const sample = waveData[i];
                    maxHeight = Math.max(maxHeight, sample.height || 0);
                    if (isColorWaveform) {
                        maxR = Math.max(maxR, sample.r || 0);
                        maxG = Math.max(maxG, sample.g || 0);
                        maxB = Math.max(maxB, sample.b || 0);
                    }
                }
                
                if (maxHeight === 0) continue;
                
                const barHeight = (maxHeight / 255) * height * 0.85;
                const y = (height - barHeight) / 2;
                const barWidth = 1.2;
                const radius = Math.min(barWidth / 2, 1.5);
                
                if (isColorWaveform) {
                    const brightness = Math.max(maxR, maxG, maxB) / 255;
                    ctx.fillStyle = `rgba(${maxR}, ${maxG}, ${maxB}, ${0.8 + brightness * 0.2})`;
                } else {
                    const intensity = maxHeight / 255;
                    ctx.fillStyle = `rgba(0, 217, 255, ${0.7 + intensity * 0.3})`;
                }
                
                if (barHeight > radius * 2) {
                    this.drawRoundedBar(ctx, x - barWidth / 2, y, barWidth, barHeight, radius);
                } else {
                    ctx.fillRect(x - barWidth / 2, y, barWidth, barHeight);
                }
            }
        } else {
            const step = width / waveData.length;
            for (let i = 0; i < waveData.length; i++) {
                const sample = waveData[i];
                const x = i * step;
                const barHeight = (sample.height / 255) * height * 0.85;
                const y = (height - barHeight) / 2;
                const barWidth = Math.max(1, step);
                const radius = Math.min(barWidth / 2, 1.5);
                
                if (isColorWaveform) {
                    const brightness = Math.max(sample.r, sample.g, sample.b) / 255;
                    ctx.fillStyle = `rgba(${sample.r}, ${sample.g}, ${sample.b}, ${0.8 + brightness * 0.2})`;
                } else {
                    const intensity = sample.height / 255;
                    ctx.fillStyle = `rgba(0, 217, 255, ${0.7 + intensity * 0.3})`;
                }
                
                if (barHeight > radius * 2) {
                    this.drawRoundedBar(ctx, x, y, barWidth, barHeight, radius);
                } else {
                    ctx.fillRect(x, y, barWidth, barHeight);
                }
            }
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
        
        const waveData = this.waveformData.preview_data || this.waveformData.color_data;
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
        
        const isColorWaveform = visibleData[0] && visibleData[0].r !== undefined;
        const samplesPerPixel = visibleData.length / width;
        
        if (samplesPerPixel > 1) {
            for (let x = 0; x < width; x++) {
                const sampleStart = Math.floor(x * samplesPerPixel);
                const sampleEnd = Math.min(visibleData.length, Math.ceil((x + 1) * samplesPerPixel));
                
                let maxHeight = 0;
                let maxR = 0, maxG = 0, maxB = 0;
                
                for (let i = sampleStart; i < sampleEnd; i++) {
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
                
                const barHeight = (maxHeight / 255) * height * 0.85;
                const y = (height - barHeight) / 2;
                const barWidth = 1.2;
                const radius = Math.min(barWidth / 2, 1.5);
                
                if (isColorWaveform) {
                    const brightness = Math.max(maxR, maxG, maxB) / 255;
                    ctx.fillStyle = `rgba(${maxR}, ${maxG}, ${maxB}, ${0.8 + brightness * 0.2})`;
                } else {
                    const intensity = maxHeight / 255;
                    ctx.fillStyle = `rgba(0, 217, 255, ${0.7 + intensity * 0.3})`;
                }
                
                if (barHeight > radius * 2) {
                    this.drawRoundedBar(ctx, x - barWidth / 2, y, barWidth, barHeight, radius);
                } else {
                    ctx.fillRect(x - barWidth / 2, y, barWidth, barHeight);
                }
            }
        } else {
            const step = width / visibleData.length;
            for (let i = 0; i < visibleData.length; i++) {
                const sample = visibleData[i];
                const x = i * step;
                const barHeight = (sample.height / 255) * height * 0.85;
                const y = (height - barHeight) / 2;
                const barWidth = Math.max(1, step);
                const radius = Math.min(barWidth / 2, 1.5);
                
                if (isColorWaveform) {
                    const brightness = Math.max(sample.r, sample.g, sample.b) / 255;
                    ctx.fillStyle = `rgba(${sample.r}, ${sample.g}, ${sample.b}, ${0.8 + brightness * 0.2})`;
                } else {
                    const intensity = sample.height / 255;
                    ctx.fillStyle = `rgba(0, 217, 255, ${0.7 + intensity * 0.3})`;
                }
                
                if (barHeight > radius * 2) {
                    this.drawRoundedBar(ctx, x, y, barWidth, barHeight, radius);
                } else {
                    ctx.fillRect(x, y, barWidth, barHeight);
                }
            }
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
