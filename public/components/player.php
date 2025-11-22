<?php if ($data): ?>
<div class="app-container rounded-lg mb-6">

        <!-- Waveform Section (Separated for Beat Matching) -->
        <div class="waveform-beatmatch-section">
            <div class="waveform-beatmatch-header">
                <div class="waveform-zoom-controls">
                    <button class="zoom-btn" onclick="window.dualPlayer.zoomBothDecks(-1);" title="Zoom Out Both Decks">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-level" id="sharedZoomLevel">16x</span>
                    <button class="zoom-btn" onclick="window.dualPlayer.zoomBothDecks(1);" title="Zoom In Both Decks">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>
                <div class="notification-area" id="syncNotification"></div>
            </div>
            <div class="waveform-beatmatch-container">
                <!-- Waveform A -->
                <div class="waveform-container-player" id="waveformContainerA">
                    <canvas id="waveformCanvasA" class="waveform-canvas"></canvas>
                    <div class="playhead">
                        <div class="playhead-time" id="timeDisplayA">00:00 / 00:00</div>
                    </div>
                    <div class="cue-markers" id="cueMarkersA"></div>
                </div>

                <!-- Waveform B -->
                <div class="waveform-container-player" id="waveformContainerB">
                    <canvas id="waveformCanvasB" class="waveform-canvas"></canvas>
                    <div class="playhead">
                        <div class="playhead-time" id="timeDisplayB">00:00 / 00:00</div>
                    </div>
                    <div class="cue-markers" id="cueMarkersB"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12">
            <!-- Deck A -->
            <div class="deck-container deck-a col-span-5" data-deck="a">
                <div class="deck-header">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="deck-label">DECK A</div>
                            <button class="eject-btn" onclick="window.dualPlayer.ejectDeck('a')" title="Eject Track">
                                <i class="fas fa-eject"></i>
                            </button>
                            <button class="master-btn" id="masterBtnA" onclick="window.dualPlayer.setMasterDeck('a')" title="Set as Master Deck">
                                <i class="fas fa-crown"></i>
                                <span>MASTER</span>
                            </button>
                            <button class="sync-btn-compact" id="bpmSyncA" onclick="window.dualPlayer.toggleBPMSync('a')" title="BPM Sync (Toggle)">
                                <i class="fas fa-sync-alt"></i>
                                <span>BPM SYNC</span>
                            </button>
                            <button class="beatsync-btn-compact" id="beatSyncA" onclick="window.dualPlayer.syncToMaster('a', 'beat')" title="Beat Sync (Snap to Beat Grid)">
                                <i class="fas fa-wave-square"></i>
                                <span>BEAT SYNC</span>
                            </button>
                            <button class="quantize-btn" id="quantizeA" onclick="window.dualPlayer.toggleQuantize('a')" title="Quantize (Snap to Beat)">
                                <i class="fas fa-magnet"></i>
                                <span>Q</span>
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

                <div class="hot-cue-pads">
                    <div class="hot-cue-label">HOT CUES</div>
                    <div class="hot-cue-grid">
                        <?php for ($i = 0; $i < 8; $i++): ?>
                        <button class="hot-cue-pad" data-deck="a" data-cue="<?= $i ?>" onclick="window.dualPlayer.triggerHotCue('a', <?= $i ?>)">
                            <div class="cue-number"><?= chr(65 + $i) ?></div>
                            <div class="cue-time" id="cueTimeA<?= $i ?>">--:--</div>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-center gap-6 col-span-2">   
                <!-- Volume Fader A -->
                <div class="volume-fader-wrapper">
                    <div class="volume-fader-control">
                        <span class="volume-value-vertical" id="volumeValueA">100%</span>
                        <input type="range" class="volume-slider-vertical" id="volumeSliderA" 
                               min="0" max="100" step="1" value="100"
                               orient="vertical"
                               oninput="window.dualPlayer.setVolume('a', this.value)">
                    </div>
                </div>
                <!-- Volume Fader B -->
                <div class="volume-fader-wrapper">
                    <div class="volume-fader-control">
                        <span class="volume-value-vertical" id="volumeValueB">100%</span>
                        <input type="range" class="volume-slider-vertical" id="volumeSliderB" 
                               min="0" max="100" step="1" value="100"
                               orient="vertical"
                               oninput="window.dualPlayer.setVolume('b', this.value)">
                    </div>
                </div>
            </div>
            <!-- Deck B -->
            <div class="deck-container deck-b col-span-5" data-deck="b">
                <div class="deck-header">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="deck-label deck-label-b">DECK B</div>
                            <button class="eject-btn" onclick="window.dualPlayer.ejectDeck('b')" title="Eject Track">
                                <i class="fas fa-eject"></i>
                            </button>
                            <button class="master-btn" id="masterBtnB" onclick="window.dualPlayer.setMasterDeck('b')" title="Set as Master Deck">
                                <i class="fas fa-crown"></i>
                                <span>MASTER</span>
                            </button>
                            <button class="sync-btn-compact" id="bpmSyncB" onclick="window.dualPlayer.toggleBPMSync('b')" title="BPM Sync (Toggle)">
                                <i class="fas fa-sync-alt"></i>
                                <span>BPM SYNC</span>
                            </button>
                            <button class="beatsync-btn-compact" id="beatSyncB" onclick="window.dualPlayer.syncToMaster('b', 'beat')" title="Beat Sync (Snap to Beat Grid)">
                                <i class="fas fa-wave-square"></i>
                                <span>BEAT SYNC</span>
                            </button>
                            <button class="quantize-btn" id="quantizeB" onclick="window.dualPlayer.toggleQuantize('b')" title="Quantize (Snap to Beat)">
                                <i class="fas fa-magnet"></i>
                                <span>Q</span>
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

                <div class="hot-cue-pads">
                    <div class="hot-cue-label">HOT CUES</div>
                    <div class="hot-cue-grid">
                        <?php for ($i = 0; $i < 8; $i++): ?>
                        <button class="hot-cue-pad" data-deck="b" data-cue="<?= $i ?>" onclick="window.dualPlayer.triggerHotCue('b', <?= $i ?>)">
                            <div class="cue-number"><?= chr(65 + $i) ?></div>
                            <div class="cue-time" id="cueTimeB<?= $i ?>">--:--</div>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

    
</div>
<?php endif; ?>
