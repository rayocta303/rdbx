<?php if ($data): ?>
<div class="mixxx-container rounded-lg mb-6">
    <div class="p-4 border-b-2 border-cyan-600 bg-gradient-to-r from-gray-800 to-gray-900">
        <h2 class="text-xl font-bold deck-title flex items-center gap-2">
            <i class="fas fa-play-circle"></i>
            <span>Dual Deck Player</span>
        </h2>
    </div>
    
    <div class="p-6 bg-gray-900">
        <div class="grid grid-cols-2 gap-4">
            <!-- Deck A -->
            <div class="deck-container deck-a" data-deck="a">
                <div class="deck-header">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="deck-label">DECK A</div>
                            <button class="eject-btn" onclick="window.dualPlayer.ejectDeck('a')" title="Eject Track">
                                <i class="fas fa-eject"></i>
                            </button>
                            <button class="sync-btn-compact" onclick="window.dualPlayer.syncBPM('a', 'b', false)" title="Sync Tempo Only">
                                <i class="fas fa-sync-alt"></i>
                                <span>→ B</span>
                            </button>
                            <button class="beatsync-btn-compact" onclick="window.dualPlayer.syncBPM('a', 'b', true)" title="Beat Sync (Tempo + Beat Grid)">
                                <i class="fas fa-wave-square"></i>
                                <span>⚡B</span>
                            </button>
                        </div>
                        <div class="deck-controls">
                            <button class="control-btn play-btn" onclick="window.dualPlayer.togglePlay('a')" title="Play/Pause">
                                <i class="fas fa-play" id="playIconA"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="track-info-compact" id="trackInfoA">
                        <div class="track-title-compact">No Track Loaded</div>
                        <div class="track-meta-compact">
                            <span class="bpm-display">--.- BPM</span>
                            <span class="key-display">--</span>
                        </div>
                    </div>
                </div>

                <div class="waveform-section">
                    <div class="waveform-controls">
                        <button class="zoom-btn" onclick="window.dualPlayer.zoomWaveform('a', -1)" title="Zoom Out">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <span class="zoom-level" id="zoomLevelA">16x</span>
                        <button class="zoom-btn" onclick="window.dualPlayer.zoomWaveform('a', 1)" title="Zoom In">
                            <i class="fas fa-search-plus"></i>
                        </button>
                        <button class="quantize-btn" id="quantizeA" onclick="window.dualPlayer.toggleQuantize('a')" title="Quantize (Snap to Beat)">
                            <i class="fas fa-magnet"></i>
                            <span>Q</span>
                        </button>
                        <div class="time-display" id="timeDisplayA">00:00 / 00:00</div>
                    </div>
                    
                    <div class="pitch-control">
                        <div class="pitch-header">
                            <span class="pitch-label">TEMPO</span>
                            <button class="master-tempo-btn" id="masterTempoA" onclick="window.dualPlayer.toggleMasterTempo('a')" title="Master Tempo (Key Lock)">
                                <i class="fas fa-lock-open"></i>
                            </button>
                            <span class="pitch-value" id="pitchValueA">0.0%</span>
                        </div>
                        <input type="range" class="pitch-slider" id="pitchSliderA" 
                               min="-16" max="16" step="0.1" value="0"
                               oninput="window.dualPlayer.setPitch('a', this.value)">
                        <div class="pitch-markers">
                            <span>-16%</span>
                            <span>0</span>
                            <span>+16%</span>
                        </div>
                        <div class="nudge-controls">
                            <button class="nudge-btn" 
                                    onmousedown="window.dualPlayer.startNudge('a', -1)" 
                                    onmouseup="window.dualPlayer.stopNudge('a')" 
                                    onmouseleave="window.dualPlayer.stopNudge('a')"
                                    title="Temporary Slow Down (Beat Match)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="nudge-label">NUDGE</span>
                            <button class="nudge-btn" 
                                    onmousedown="window.dualPlayer.startNudge('a', 1)" 
                                    onmouseup="window.dualPlayer.stopNudge('a')" 
                                    onmouseleave="window.dualPlayer.stopNudge('a')"
                                    title="Temporary Speed Up (Beat Match)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="volume-control">
                        <div class="volume-header">
                            <i class="fas fa-volume-up"></i>
                            <span class="volume-label">VOLUME</span>
                            <span class="volume-value" id="volumeValueA">100%</span>
                        </div>
                        <input type="range" class="volume-slider" id="volumeSliderA" 
                               min="0" max="100" step="1" value="100"
                               oninput="window.dualPlayer.setVolume('a', this.value)">
                    </div>
                    
                    <div class="waveform-container-player" id="waveformContainerA">
                        <canvas id="waveformCanvasA" class="waveform-canvas"></canvas>
                        <div class="playhead"></div>
                        <div class="cue-markers" id="cueMarkersA"></div>
                    </div>
                </div>

                <div class="hot-cue-pads">
                    <div class="hot-cue-label">HOT CUES</div>
                    <div class="hot-cue-grid">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                        <button class="hot-cue-pad" data-deck="a" data-cue="<?= $i ?>" onclick="window.dualPlayer.triggerHotCue('a', <?= $i ?>)">
                            <div class="cue-number"><?= $i ?></div>
                            <div class="cue-time" id="cueTimeA<?= $i ?>">--:--</div>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Deck B -->
            <div class="deck-container deck-b" data-deck="b">
                <div class="deck-header">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="deck-label deck-label-b">DECK B</div>
                            <button class="eject-btn" onclick="window.dualPlayer.ejectDeck('b')" title="Eject Track">
                                <i class="fas fa-eject"></i>
                            </button>
                            <button class="sync-btn-compact" onclick="window.dualPlayer.syncBPM('b', 'a', false)" title="Sync Tempo Only">
                                <i class="fas fa-sync-alt"></i>
                                <span>→ A</span>
                            </button>
                            <button class="beatsync-btn-compact" onclick="window.dualPlayer.syncBPM('b', 'a', true)" title="Beat Sync (Tempo + Beat Grid)">
                                <i class="fas fa-wave-square"></i>
                                <span>⚡A</span>
                            </button>
                        </div>
                        <div class="deck-controls">
                            <button class="control-btn play-btn" onclick="window.dualPlayer.togglePlay('b')" title="Play/Pause">
                                <i class="fas fa-play" id="playIconB"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="track-info-compact" id="trackInfoB">
                        <div class="track-title-compact">No Track Loaded</div>
                        <div class="track-meta-compact">
                            <span class="bpm-display">--.- BPM</span>
                            <span class="key-display">--</span>
                        </div>
                    </div>
                </div>

                <div class="waveform-section">
                    <div class="waveform-controls">
                        <button class="zoom-btn" onclick="window.dualPlayer.zoomWaveform('b', -1)" title="Zoom Out">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <span class="zoom-level" id="zoomLevelB">16x</span>
                        <button class="zoom-btn" onclick="window.dualPlayer.zoomWaveform('b', 1)" title="Zoom In">
                            <i class="fas fa-search-plus"></i>
                        </button>
                        <button class="quantize-btn" id="quantizeB" onclick="window.dualPlayer.toggleQuantize('b')" title="Quantize (Snap to Beat)">
                            <i class="fas fa-magnet"></i>
                            <span>Q</span>
                        </button>
                        <div class="time-display" id="timeDisplayB">00:00 / 00:00</div>
                    </div>
                    
                    <div class="pitch-control">
                        <div class="pitch-header">
                            <span class="pitch-label">TEMPO</span>
                            <button class="master-tempo-btn" id="masterTempoB" onclick="window.dualPlayer.toggleMasterTempo('b')" title="Master Tempo (Key Lock)">
                                <i class="fas fa-lock-open"></i>
                            </button>
                            <span class="pitch-value" id="pitchValueB">0.0%</span>
                        </div>
                        <input type="range" class="pitch-slider" id="pitchSliderB" 
                               min="-16" max="16" step="0.1" value="0"
                               oninput="window.dualPlayer.setPitch('b', this.value)">
                        <div class="pitch-markers">
                            <span>-16%</span>
                            <span>0</span>
                            <span>+16%</span>
                        </div>
                        <div class="nudge-controls">
                            <button class="nudge-btn" 
                                    onmousedown="window.dualPlayer.startNudge('b', -1)" 
                                    onmouseup="window.dualPlayer.stopNudge('b')" 
                                    onmouseleave="window.dualPlayer.stopNudge('b')"
                                    title="Temporary Slow Down (Beat Match)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="nudge-label">NUDGE</span>
                            <button class="nudge-btn" 
                                    onmousedown="window.dualPlayer.startNudge('b', 1)" 
                                    onmouseup="window.dualPlayer.stopNudge('b')" 
                                    onmouseleave="window.dualPlayer.stopNudge('b')"
                                    title="Temporary Speed Up (Beat Match)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="volume-control">
                        <div class="volume-header">
                            <i class="fas fa-volume-up"></i>
                            <span class="volume-label">VOLUME</span>
                            <span class="volume-value" id="volumeValueB">100%</span>
                        </div>
                        <input type="range" class="volume-slider" id="volumeSliderB" 
                               min="0" max="100" step="1" value="100"
                               oninput="window.dualPlayer.setVolume('b', this.value)">
                    </div>
                    
                    <div class="waveform-container-player" id="waveformContainerB">
                        <canvas id="waveformCanvasB" class="waveform-canvas"></canvas>
                        <div class="playhead"></div>
                        <div class="cue-markers" id="cueMarkersB"></div>
                    </div>
                </div>

                <div class="hot-cue-pads">
                    <div class="hot-cue-label">HOT CUES</div>
                    <div class="hot-cue-grid">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                        <button class="hot-cue-pad" data-deck="b" data-cue="<?= $i ?>" onclick="window.dualPlayer.triggerHotCue('b', <?= $i ?>)">
                            <div class="cue-number"><?= $i ?></div>
                            <div class="cue-time" id="cueTimeB<?= $i ?>">--:--</div>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
