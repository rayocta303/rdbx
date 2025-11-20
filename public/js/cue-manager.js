class CueManager {
    constructor(overviewCanvasId, detailedCanvasId, cueListId) {
        this.overviewCanvas = document.getElementById(overviewCanvasId);
        this.detailedCanvas = document.getElementById(detailedCanvasId);
        this.cueListContainer = document.getElementById(cueListId);
        this.cuePoints = [];
        this.duration = 0;
        this.detailedZoom = 10;
        this.detailedScrollOffset = 0;
        this.onCueClickCallback = null;
        
        this.hotCueColors = [
            '#FF0000', '#FF6600', '#FFCC00', '#00FF00',
            '#00CCFF', '#0066FF', '#CC00FF', '#FF0099'
        ];
    }
    
    loadCues(cuePoints, duration) {
        this.cuePoints = cuePoints || [];
        this.duration = duration;
        this.renderCueList();
    }
    
    renderCueList() {
        if (!this.cueListContainer) return;
        
        if (this.cuePoints.length === 0) {
            this.cueListContainer.innerHTML = '<div class="text-center text-gray-500 py-4">No cue points</div>';
            return;
        }
        
        let html = '<div class="space-y-2">';
        
        this.cuePoints.forEach((cue, index) => {
            const isHotCue = cue.hot_cue > 0;
            const cueName = isHotCue ? `Hot Cue ${String.fromCharCode(64 + cue.hot_cue)}` : `Memory ${index + 1}`;
            const color = isHotCue ? this.hotCueColors[(cue.hot_cue - 1) % this.hotCueColors.length] : '#888888';
            const timeStr = this.formatTime(cue.time / 1000);
            
            html += `
                <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded cursor-pointer group" onclick="window.trackDetailPanel.jumpToCue(${cue.time / 1000})">
                    <div class="w-12 h-8 rounded flex items-center justify-center text-white text-xs font-bold" style="background-color: ${color}">
                        ${isHotCue ? String.fromCharCode(64 + cue.hot_cue) : 'M'}
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-gray-800">${cueName}</div>
                        ${cue.comment ? `<div class="text-xs text-gray-600 italic">${this.escapeHtml(cue.comment)}</div>` : ''}
                    </div>
                    <div class="text-sm text-gray-600 font-mono">${timeStr}</div>
                </div>
            `;
        });
        
        html += '</div>';
        this.cueListContainer.innerHTML = html;
    }
    
    renderCuesOnWaveform(waveformRenderer) {
        if (!this.overviewCanvas || !this.detailedCanvas) return;
        
        this.renderOverviewCues();
        this.renderDetailedCues(waveformRenderer.detailedScrollOffset, waveformRenderer.detailedZoom);
    }
    
    renderOverviewCues() {
        if (!this.overviewCanvas || !this.duration) return;
        
        const ctx = this.overviewCanvas.getContext('2d');
        const width = this.overviewCanvas.width;
        const height = this.overviewCanvas.height;
        
        this.cuePoints.forEach((cue) => {
            const x = (cue.time / 1000 / this.duration) * width;
            const isHotCue = cue.hot_cue > 0;
            const color = isHotCue ? this.hotCueColors[(cue.hot_cue - 1) % this.hotCueColors.length] : '#888888';
            
            if (isHotCue) {
                ctx.fillStyle = color;
                ctx.globalAlpha = 0.5;
                ctx.fillRect(x - 2, 0, 4, height);
                ctx.globalAlpha = 1.0;
                
                ctx.fillStyle = color;
                ctx.fillRect(x - 1, 0, 2, 12);
            } else {
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.setLineDash([4, 4]);
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, height);
                ctx.stroke();
                ctx.setLineDash([]);
            }
        });
    }
    
    renderDetailedCues(scrollOffset, zoom) {
        if (!this.detailedCanvas || !this.duration) return;
        
        this.detailedScrollOffset = scrollOffset;
        this.detailedZoom = zoom;
        
        const ctx = this.detailedCanvas.getContext('2d');
        const width = this.detailedCanvas.width;
        const height = this.detailedCanvas.height;
        
        const visibleDuration = this.duration / zoom;
        
        this.cuePoints.forEach((cue) => {
            const cueTime = cue.time / 1000;
            const relativeTime = cueTime - scrollOffset;
            
            if (relativeTime >= 0 && relativeTime <= visibleDuration) {
                const x = (relativeTime / visibleDuration) * width;
                const isHotCue = cue.hot_cue > 0;
                const color = isHotCue ? this.hotCueColors[(cue.hot_cue - 1) % this.hotCueColors.length] : '#888888';
                
                if (isHotCue) {
                    ctx.fillStyle = color;
                    ctx.globalAlpha = 0.4;
                    ctx.fillRect(x - 4, 0, 8, height);
                    ctx.globalAlpha = 1.0;
                    
                    ctx.fillStyle = color;
                    ctx.fillRect(x - 2, 0, 4, 20);
                    
                    ctx.fillStyle = '#FFFFFF';
                    ctx.font = 'bold 12px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText(String.fromCharCode(64 + cue.hot_cue), x, 15);
                } else {
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 2;
                    ctx.setLineDash([4, 4]);
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, height);
                    ctx.stroke();
                    ctx.setLineDash([]);
                }
            }
        });
    }
    
    formatTime(seconds) {
        if (!isFinite(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    onCueClick(callback) {
        this.onCueClickCallback = callback;
    }
}
