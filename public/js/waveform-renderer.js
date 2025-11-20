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
            this.overviewCanvas.width = this.overviewCanvas.offsetWidth * 2;
            this.overviewCanvas.height = 120;
            
            this.overviewCanvas.addEventListener('click', (e) => {
                this.handleOverviewClick(e);
            });
        }
        
        if (this.detailedCanvas) {
            this.detailedCanvas.width = this.detailedCanvas.offsetWidth * 2;
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
        
        this.renderOverview();
        this.renderDetailed();
    }
    
    renderOverview() {
        if (!this.overviewCanvas || !this.waveformData) return;
        
        const ctx = this.overviewCanvas.getContext('2d');
        const width = this.overviewCanvas.width;
        const height = this.overviewCanvas.height;
        
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, width, height);
        
        const waveData = this.waveformData.preview_data || this.waveformData.color_data;
        if (!waveData || waveData.length === 0) return;
        
        const step = width / waveData.length;
        const isColorWaveform = waveData[0].r !== undefined;
        
        waveData.forEach((sample, i) => {
            const x = i * step;
            const barHeight = (sample.height / 255) * height * 0.9;
            const y = (height - barHeight) / 2;
            
            if (isColorWaveform) {
                ctx.fillStyle = `rgb(${sample.r}, ${sample.g}, ${sample.b})`;
            } else {
                ctx.fillStyle = '#00D9FF';
            }
            
            ctx.fillRect(x, y, Math.max(1, step), barHeight);
        });
        
        this.drawOverviewPlayhead();
    }
    
    renderDetailed() {
        if (!this.detailedCanvas || !this.waveformData) return;
        
        const ctx = this.detailedCanvas.getContext('2d');
        const width = this.detailedCanvas.width;
        const height = this.detailedCanvas.height;
        
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, width, height);
        
        const waveData = this.waveformData.preview_data || this.waveformData.color_data;
        if (!waveData || waveData.length === 0) return;
        
        const visibleDuration = this.duration / this.detailedZoom;
        const startTime = this.detailedScrollOffset;
        const endTime = startTime + visibleDuration;
        
        const startIndex = Math.floor((startTime / this.duration) * waveData.length);
        const endIndex = Math.ceil((endTime / this.duration) * waveData.length);
        const visibleData = waveData.slice(startIndex, endIndex);
        
        const step = width / visibleData.length;
        const isColorWaveform = visibleData[0] && visibleData[0].r !== undefined;
        
        visibleData.forEach((sample, i) => {
            const x = i * step;
            const barHeight = (sample.height / 255) * height * 0.9;
            const y = (height - barHeight) / 2;
            
            if (isColorWaveform) {
                ctx.fillStyle = `rgb(${sample.r}, ${sample.g}, ${sample.b})`;
            } else {
                ctx.fillStyle = '#00D9FF';
            }
            
            ctx.fillRect(x, y, Math.max(1, step), barHeight);
        });
        
        this.drawDetailedPlayhead();
    }
    
    drawOverviewPlayhead() {
        if (!this.overviewCanvas || !this.duration) return;
        
        const ctx = this.overviewCanvas.getContext('2d');
        const width = this.overviewCanvas.width;
        const height = this.overviewCanvas.height;
        
        const x = (this.playheadPosition / this.duration) * width;
        
        ctx.strokeStyle = '#FF0000';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, height);
        ctx.stroke();
        
        ctx.fillStyle = '#FF0000';
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
            
            ctx.strokeStyle = '#FF0000';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
            
            ctx.fillStyle = '#FF0000';
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
