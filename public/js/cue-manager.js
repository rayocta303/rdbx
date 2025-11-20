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
            this.cueListContainer.innerHTML = `
                <div class="text-center text-gray-500 py-6">
                    <i class="fas fa-map-marker-alt text-3xl mb-2 opacity-30"></i>
                    <div class="text-sm">No hot cues or memory points</div>
                </div>
            `;
            return;
        }
        
        let html = '<div class="space-y-2">';
        
        this.cuePoints.forEach((cue, index) => {
            const isHotCue = cue.hot_cue > 0;
            const cueName = isHotCue ? `HOT CUE ${String.fromCharCode(64 + cue.hot_cue)}` : `MEMORY ${index + 1}`;
            const color = isHotCue ? this.hotCueColors[(cue.hot_cue - 1) % this.hotCueColors.length] : '#6B7280';
            const timeStr = this.formatTime(cue.time / 1000);
            const typeIcon = isHotCue ? 'fa-circle' : 'fa-bookmark';
            
            html += `
                <div class="flex items-stretch gap-3 cue-pad cursor-pointer group hover:scale-102 transition-all" 
                     onclick="window.trackDetailPanel.jumpToCue(${cue.time / 1000})"
                     style="border-left: 4px solid ${color};">
                    <div class="flex items-center justify-center px-3 py-2" 
                         style="background: linear-gradient(135deg, ${color}80 0%, ${color}40 100%);">
                        <div class="text-center">
                            <i class="fas ${typeIcon} text-white text-lg mb-1"></i>
                            <div class="text-white text-xs font-bold">
                                ${isHotCue ? String.fromCharCode(64 + cue.hot_cue) : 'M'}
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 py-2 pr-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-cyan-400 uppercase tracking-wide">${cueName}</div>
                                ${cue.comment ? `<div class="text-xs text-gray-400 mt-0.5">${this.escapeHtml(cue.comment)}</div>` : ''}
                            </div>
                            <div class="text-sm text-gray-300 font-mono font-semibold">${timeStr}</div>
                        </div>
                    </div>
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
