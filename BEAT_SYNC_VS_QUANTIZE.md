# Beat Sync vs Quantize - Explained

## Konsep Fundamental DJ Engine

### 1. Beat Sync = Menyamakan Tempo & Fase (Global Control)

**Tugas Beat Sync:**
- ✅ Menyamakan BPM antar track
- ✅ Menggeser posisi lagu agar beat-nya sejajar dengan track master
- ✅ Menjaga keduanya tetap sinkron selama playback

**Intinya:**
> Beat Sync mengatur **posisi & kecepatan lagu secara global**.

**Implementasi di Code:**
```javascript
// File: public/js/dual-player.js

syncToMaster(targetDeckId, mode) {
    // Step 1: Tentukan master & target
    const sourceDeckId = this.masterDeck;
    const sourceDeck = this.decks[sourceDeckId];
    const targetDeck = this.decks[targetDeckId];
    
    // Step 2: Sync BPM (Tempo)
    const snapBeats = mode === "beat";
    this.syncBPM(sourceDeckId, targetDeckId, snapBeats);
    
    // Step 3: Snap Beat Grid (Phase) - jika mode "beat"
    if (snapBeats) {
        this.snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM);
    }
}
```

**Beat Sync Flow:**
```
User Click BEAT SYNC
    ↓
1. Hitung BPM master (dengan pitch adjustment)
    ↓
2. Adjust pitch slider target agar BPM sama
    ↓
3. Cari beat terdekat ke CENTER POINT pada kedua deck
    ↓
4. Geser target deck agar beats sejajar di center
    ↓
5. Phase locked - kedua deck sync
```

---

### 2. Quantize = Mengunci Aksi ke Beat Grid (Precision Control)

**Tugas Quantize:**
- ✅ Mengunci semua aksi pengguna ke beat grid terdekat
  - Hot cue triggers
  - Loop in/out points
  - Effect triggers
  - Manual seeking

**Intinya:**
> Quantize mengatur **ketepatan event/aksi terhadap beat grid**.

**Implementasi di Code:**
```javascript
// File: public/js/dual-player.js

quantizeTime(deckId, targetTime) {
    const deck = this.decks[deckId];
    
    // Return original time jika Quantize OFF
    if (!deck.quantizeEnabled || !deck.track || !deck.originalBPM) {
        return targetTime;
    }
    
    // Hitung beat grid
    const currentBPM = deck.originalBPM * (1 + deck.pitchValue / 100);
    const beatLength = 60 / currentBPM;
    
    // Get first beat offset dari Rekordbox beatgrid
    let firstBeatOffset = 0;
    if (deck.beatgridData && deck.beatgridData.length > 0) {
        firstBeatOffset = deck.beatgridData[0].time;
    }
    
    // Snap ke beat terdekat
    const timeFromFirstBeat = targetTime - firstBeatOffset;
    const beatNumber = Math.round(timeFromFirstBeat / beatLength);
    const quantizedTime = firstBeatOffset + beatNumber * beatLength;
    
    return Math.max(0, Math.min(quantizedTime, deck.duration));
}

// Digunakan untuk Hot Cue Triggers
triggerHotCue(deckId, cueNumber) {
    const deck = this.decks[deckId];
    const cueData = deck.hotCues[cueNumber];
    
    if (cueData && cueData.time !== undefined) {
        let targetTime = cueData.time;
        
        // Snap ke beat terdekat jika Quantize ON
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
```

**Quantize Flow:**
```
User Triggers Hot Cue (atau aksi lain)
    ↓
Quantize Enabled?
    ↓
YES → Hitung beat terdekat
    ↓
      Snap ke beat grid
    ↓
      Execute action di beat yang tepat
    ↓
NO → Execute action langsung di posisi asli
```

---

## Hubungan Logisnya (Flow DJ Engine)

### Skenario 1: Beat Sync + Quantize Aktif

```
┌─────────────────────────────────────────────────┐
│ STEP 1: Beat Sync (Global Alignment)           │
├─────────────────────────────────────────────────┤
│ • Deck A: Playing at 49.717s                    │
│ • Deck B: Playing at 45.327s                    │
│                                                 │
│ User clicks BEAT SYNC on Deck A                │
│                                                 │
│ Result:                                         │
│ • BPM synced: Both 128.00 BPM                  │
│ • Beats aligned to center point                │
│ • Deck A adjusted to 45.156s                   │
│ • Adjustment: -171ms                           │
└─────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│ STEP 2: Quantize (Action Precision)            │
├─────────────────────────────────────────────────┤
│ • User presses Hot Cue A (stored at 10.234s)   │
│                                                 │
│ Quantize ON:                                    │
│ • Find nearest beat to 10.234s                 │
│ • Snap to 10.280s (exact beat position)        │
│ • Jump happens precisely on beat               │
│                                                 │
│ Result:                                         │
│ • No phase slip                                │
│ • Action stays in-sync with Deck B             │
│ • Perfect mix maintained                       │
└─────────────────────────────────────────────────┘
```

### Skenario 2: Beat Sync Aktif, Quantize Mati

```
┌─────────────────────────────────────────────────┐
│ STEP 1: Beat Sync (Global Alignment) - SAMA    │
├─────────────────────────────────────────────────┤
│ • Tracks synced, beats aligned                  │
└─────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│ STEP 2: No Quantize (Free Action)              │
├─────────────────────────────────────────────────┤
│ • User presses Hot Cue A (stored at 10.234s)   │
│                                                 │
│ Quantize OFF:                                   │
│ • Jump langsung ke 10.234s (exact storage)     │
│ • Mungkin terjadi di tengah beat               │
│                                                 │
│ Result:                                         │
│ • Phase slip terjadi                           │
│ • Tracks keluar dari sync                      │
│ • Perlu Beat Sync lagi                         │
└─────────────────────────────────────────────────┘
```

---

## Hasilnya

Dengan **Beat Sync + Quantize** aktif bersamaan:

✅ **Track sejajar** - Beats aligned di center point  
✅ **Aksi tetap presisi** - Semua action snap ke beat grid  
✅ **Tidak ada drift/slip** - Phase locked sepanjang waktu

---

## Implementasi Teknis

### Beat Sync Algorithm (Center Point Alignment)

```javascript
snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM) {
    // 1. TENTUKAN CENTER POINT
    const sourceCenterPoint = sourceDeck.audio.currentTime;
    const targetCenterPoint = targetDeck.audio.currentTime;
    
    // 2. DETEKSI BEAT GRID AKTIF
    const sourceNearestBeatTime = 
        sourceFirstBeatOffset + 
        (Math.round((sourceCenterPoint - sourceFirstBeatOffset) / sourceBeatLength) * sourceBeatLength);
    
    const targetNearestBeatTime = 
        targetFirstBeatOffset + 
        (Math.round((targetCenterPoint - targetFirstBeatOffset) / targetBeatLength) * targetBeatLength);
    
    // 3. HITUNG OFFSET BEAT
    const sourceBeatOffsetFromCenter = sourceNearestBeatTime - sourceCenterPoint;
    const targetBeatOffsetFromCenter = targetNearestBeatTime - targetCenterPoint;
    const offsetDifference = sourceBeatOffsetFromCenter - targetBeatOffsetFromCenter;
    
    // 4. GESER TRACK YANG DI-SYNC
    let newTargetTime = targetCenterPoint + offsetDifference;
    newTargetTime = Math.max(0, Math.min(newTargetTime, targetDeck.duration - 0.1));
    
    if (Math.abs(offsetDifference) > 0.001) {
        targetDeck.audio.currentTime = newTargetTime;
        this.updatePlayhead(targetDeckId);
    }
    
    // 5. LOCK PHASE - BPM sudah sama, phase terjaga
}
```

### Quantize Algorithm (Beat Grid Snapping)

```javascript
quantizeTime(deckId, targetTime) {
    // Validasi
    if (!deck.quantizeEnabled || !deck.track || !deck.originalBPM) {
        return targetTime; // Return original jika OFF
    }
    
    // Hitung beat grid
    const currentBPM = deck.originalBPM * (1 + deck.pitchValue / 100);
    const beatLength = 60 / currentBPM;
    const firstBeatOffset = deck.beatgridData[0].time || 0;
    
    // Snap ke beat terdekat
    const timeFromFirstBeat = targetTime - firstBeatOffset;
    const beatNumber = Math.round(timeFromFirstBeat / beatLength);
    const quantizedTime = firstBeatOffset + beatNumber * beatLength;
    
    // Return snapped time
    return Math.max(0, Math.min(quantizedTime, deck.duration));
}
```

---

## Data Source

Kedua fitur menggunakan **Rekordbox Beat Grid Data**:

### PQTZ Section (ANLZ Files)

```php
// src/Parsers/AnlzParser.php
private function parsePQTZ($data, $length) {
    $entries = [];
    $pos = 0;
    
    while ($pos < $length) {
        // Big-endian format
        $beat = unpack('n', substr($data, $pos, 2))[1];
        $tempo = unpack('n', substr($data, $pos + 2, 2))[1];
        $time = unpack('N', substr($data, $pos + 4, 4))[1];
        
        $entries[] = [
            'beat' => $beat,
            'tempo' => $tempo / 100,  // BPM
            'time' => $time / 1000     // seconds
        ];
        
        $pos += 8;
    }
    
    return $entries;
}
```

**Data yang digunakan:**
- `beatgridData[0].time` - First beat offset (downbeat)
- `beatLength = 60 / BPM` - Jarak antar beat dalam detik
- `currentBPM` - BPM aktual dengan pitch adjustment

---

## Visual Representation

### Tanpa Quantize

```
Time:  10.0s      10.2s     10.4s     10.6s     10.8s
       |         |         |         |         |
Beats: ▼─────────▼─────────▼─────────▼─────────▼
       │         │         │         │         │
       │         │         │         │         │
       │    ✗ Hot Cue (10.234s)     │         │
       │         │         │         │         │

Result: Jump di tengah beat → Phase slip
```

### Dengan Quantize

```
Time:  10.0s      10.2s     10.4s     10.6s     10.8s
       |         |         |         |         |
Beats: ▼─────────▼─────────▼─────────▼─────────▼
       │         │         │         │         │
       │         │         │         │         │
       │    Hot Cue (10.234s)       │         │
       │         ↓ Snap               │         │
       │         ✓ Quantized (10.200s) │         │

Result: Jump tepat di beat → No phase slip
```

---

## Toggle States

### Beat Sync
- **Type**: Momentary button
- **Behavior**: One-time sync saat ditekan
- **Visual**: Notification + console log
- **Effect**: Global alignment

### Quantize
- **Type**: Latching toggle
- **Behavior**: ON/OFF state yang persistent
- **Visual**: Button highlight saat aktif
- **Effect**: Precision untuk semua actions

---

## Best Practices

### Workflow Rekomendasi

1. **Setup Phase:**
   ```
   - Load tracks ke Deck A & B
   - Set master deck (crown button)
   - Enable Quantize pada kedua deck
   ```

2. **Initial Sync:**
   ```
   - Play kedua tracks
   - Klik BEAT SYNC pada slave deck
   - Tracks sekarang sejajar
   ```

3. **Mixing Phase:**
   ```
   - Quantize tetap ON
   - Gunakan hot cues untuk jumping
   - Semua jump akan snap ke beat
   - No phase drift
   ```

4. **Advanced Techniques:**
   ```
   - BPM Sync (latching) untuk auto-follow pitch
   - Beat Sync untuk re-align jika terjadi drift
   - Quantize untuk loop points & effects
   ```

---

## Troubleshooting

### Beat Sync Tidak Stabil

**Symptom:** Adjustment bervariasi besar (>300ms)

**Possible Causes:**
1. Tracks belum di-analyze dengan benar di Rekordbox
2. Beat grid offset tidak akurat
3. Variable BPM tracks (live recordings)

**Solution:**
- Re-analyze tracks di Rekordbox dengan "High Precision" mode
- Manual adjust beat grid di Rekordbox
- Use BPM Sync untuk continuous adjustment

### Quantize Tidak Bekerja

**Symptom:** Hot cue tidak snap ke beat

**Check:**
1. Apakah Quantize button highlighted (ON)?
2. Apakah track memiliki beatgrid data?
3. Console log: "Quantize ON for Deck X"

**Solution:**
- Toggle Quantize ON
- Pastikan track sudah di-analyze di Rekordbox
- Check beatgridData di browser console

### Phase Drift Setelah Hot Cue

**Symptom:** Tracks keluar sync setelah trigger hot cue

**Cause:** Quantize OFF

**Solution:**
- Enable Quantize sebelum trigger hot cues
- Re-apply Beat Sync untuk re-align

---

## Console Output Examples

### Beat Sync Success

```
[Sync] Syncing Deck A TO master B
[Beat Sync - Grid Center Alignment]
  Master B: Center=49.717s | Nearest Beat=49.717s | Offset=0ms
  Target A: Center=45.327s | Nearest Beat=45.498s (before) | Offset=171ms
  → Adjustment: -171ms | New Target Time: 45.156s
  ✓ Beats aligned at center point | Phase locked
Synced A (128 BPM) to B (128.00 BPM) + Beat Grid
```

### Quantize Toggle

```
Quantize ON for Deck A
Quantize OFF for Deck A
```

### Combined Use

```
[User enables Quantize]
Quantize ON for Deck A

[User clicks Beat Sync]
[Beat Sync - Grid Center Alignment]
  → Adjustment: 69ms | New Target Time: 47.951s
  ✓ Beats aligned at center point | Phase locked

[User triggers Hot Cue A]
[Hot Cue A triggered at quantized position: 10.200s]
✓ No phase slip - tracks stay in sync
```

---

## Technical Specs

### Precision

| Feature | Resolution | Algorithm |
|---------|-----------|-----------|
| Beat Sync | 1ms (0.001s threshold) | Center Point Alignment |
| Quantize | 1ms (0.001s threshold) | Nearest Beat Rounding |
| Beat Grid | Rekordbox precision | PQTZ Section (Big-Endian) |

### Performance

| Operation | Complexity | Typical Time |
|-----------|-----------|--------------|
| Beat Sync | O(1) | <1ms |
| Quantize | O(1) | <1ms |
| Beat Grid Lookup | O(1) | <1ms |

---

**Version**: 2.2  
**Last Updated**: November 22, 2025  
**Files**: `public/js/dual-player.js` - `snapBeatsToGrid()`, `quantizeTime()`, `triggerHotCue()`
