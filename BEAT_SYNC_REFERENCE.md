# Beat Sync - Referensi dari DJ Software Profesional

## Tanggal: 22 November 2025

Dokumen ini berisi rangkuman penelitian tentang bagaimana beat sync diimplementasikan di DJ software profesional (Mixxx, Traktor, Serato).

---

## Konsep Dasar Beat Sync

### 2 Komponen Utama:

1. **Tempo Sync (BPM Matching)**
   - Menyamakan kecepatan playback antara 2 track
   - Menggunakan time-stretching / pitch shifting
   - Formula: `playbackRate = targetBPM / originalBPM`

2. **Phase Sync (Beat Alignment)**  
   - Menyamakan posisi beat antara 2 track
   - Menggeser playback position agar beats sejalan
   - Membutuhkan beat grid yang akurat

---

## Implementasi di Mixxx (Open Source Reference)

### Sync Controls

Mixxx memisahkan kontrol sync menjadi beberapa mode:

| Control | Tempo | Phase | Behavior |
|---------|-------|-------|----------|
| `sync_enabled` | ✓ | Tergantung quantize | Default sync mode |
| `beatsync_phase` | ✓ | ✓ Always | Sync phase tidak peduli quantize |
| `sync_master` | ✓ | ✓ Continuous | Locked sync dengan monitoring terus menerus |

### Quantize Integration

**Quantize ON:**
- Phase disesuaikan secara **bertahap** dengan "nudging"
- Menggunakan small rate adjustments untuk smooth alignment
- Mencegah jarring jumps saat playback

**Quantize OFF:**
- Phase disesuaikan dengan **instant seek**
- Langsung jump ke posisi beat yang tepat
- Lebih cepat tapi bisa terdengar kasar

### Half/Double BPM Handling

Mixxx menangani kasus dimana track memiliki BPM 2x atau 0.5x dari yang lain:

```cpp
// Dari synccontrol.cpp
double determineBpmMultiplier(mixxx::Bpm myBpm, mixxx::Bpm targetBpm) {
    double unityRatio = myBpm / targetBpm;
    double unityRatioSquare = unityRatio * unityRatio;
    
    if (unityRatioSquare > 2.0) {
        return 2.0;  // Double
    } else if (unityRatioSquare < 0.5) {
        return 0.5;  // Halve
    }
    
    return 1.0;  // Unity
}
```

**Catatan**: Mixxx menggunakan ratio square untuk threshold, tapi konsep dasarnya sama dengan implementasi kita.

### Phase Alignment Process

1. Hitung phase offset antara current beat position dan sync target
2. Gunakan beatgrid untuk menentukan nearest beat alignment point
3. Jika quantize ON: Gradually nudge menggunakan PI controller
4. Jika quantize OFF: Instant seek ke posisi yang tepat

---

## Implementasi di Traktor

### Sync Modes

| Mode | Tempo Lock | Phase Lock | Use Case |
|------|-----------|-----------|----------|
| **BeatSync** | ✓ | ✓ Continuous | Maintains both tempo & phase, even during scratching |
| **TempoSync** | ✓ | ✗ | Locks tempo, allows manual phase adjustment |

### Master Clock System

- Semua deck bisa sync ke **Deck Master** atau **Master Clock** (independent clock)
- Saat Master Clock berubah tempo, semua synced deck ikut berubah real-time
- BeatSync prevents phase drift - track never go out of sync

### Beatgrid Structure

- Single grid marker dengan constant tempo
- Supports multiple grid markers untuk variable tempo tracks
- White lines overlay waveforms di posisi beat

---

## Implementasi di Serato

### Dual Sync Approaches

**Simple Sync** (No beatgrids required):
- Snaps closest transients together + matches BPM
- Tidak perlu pre-analysis
- Less precise tapi lebih flexible

**Smart Sync** (Beatgrid-based):
- Requires accurate bar-structured beatgrids
- Snaps beatgrids, tempo, AND bar position
- More control and precision

### Sync Types

| Type | Description | Indicator |
|------|-------------|-----------|
| **Beat Sync** | Full sync - tempo, phase, bar position locked | Blue |
| **Tempo Sync** | Tempo matched, phase offset maintained | Gold |
| **Armed Sync** | Sync ready - engages on playback | Grey |

### Primary/Secondary Deck System

- First deck dengan sync ON menjadi Primary Deck (sets tempo)
- Subsequent decks menjadi Secondary (follow Primary)
- Adjusting platter drops from Beat Sync → Tempo Sync

---

## Key Technical Concepts

### Beat Grid

Timeline overlay yang menandai posisi beat (bukan hanya BPM × time). Beatgrid harus akurat untuk phase sync yang presisi.

### Phase Lock

Proses menjaga alignment antara beats dari 2 track:
- **Offset matching**: Align beat positions
- **Continuous monitoring**: Detect dan correct drift
- **Tempo matching**: Ensure BPM sama

### Tempo Octave Error

Kesalahan umum dimana BPM terdeteksi 2x atau 0.5x dari BPM sebenarnya:
- Track 140 BPM terdeteksi sebagai 70 BPM
- Track 70 BPM terdeteksi sebagai 140 BPM
- Perlu BPM multiplier untuk koreksi

### Time-Stretching

Algoritma untuk mengubah tempo tanpa mengubah pitch (Master Tempo / Key Lock):
- **Phase Vocoder**: Classic approach
- **PSOLA**: Time-domain approach
- **WSOLA**: Window-based approach
- **Elastique/Rubberband**: Modern commercial algorithms

---

## Common Algorithm Steps

Semua DJ software mengikuti pattern yang sama:

1. **BPM Detection**
   - FFT-based analysis
   - Onset/transient detection
   - Tempo estimation

2. **Beat Grid Construction**
   - Identify first beat
   - Calculate beat positions based on BPM
   - Place grid markers

3. **Tempo Matching**
   - Calculate required playback rate
   - Apply time-stretching
   - Adjust tempo slider/pitch

4. **Phase Alignment**
   - Calculate beat offset between decks
   - Determine nearest alignment point
   - Seek or nudge to align

5. **Continuous Monitoring** (untuk locked sync)
   - Monitor phase drift
   - Apply micro-corrections
   - Handle tempo changes

---

## Implementasi Kita vs Referensi

### ✅ Yang Sudah Benar

1. **getActualBPM()**: Menggunakan playbackRate (tidak terpengaruh Master Tempo)
2. **determineBPMMultiplier()**: Logic untuk handle half/double BPM
3. **syncBPM()**: Formula yang benar untuk tempo matching
4. **snapBeatsToGrid()**: Basic phase alignment
5. **startBeatSyncLoop()**: Continuous monitoring dengan PI controller

### ⚠️ Perbedaan dengan Referensi

1. **No Separation**: Kita tidak memisahkan tempo sync dan phase sync
   - Mixxx punya `sync_enabled` vs `beatsync_phase`
   - Traktor punya BeatSync vs TempoSync
   - Kita: Selalu sync keduanya

2. **No Quantize Integration**: Kita tidak integrate dengan quantize
   - Seharusnya: Quantize ON → gradual nudge, Quantize OFF → instant seek
   - Saat ini: Selalu gradual nudge

3. **Phase Monitoring Always Active**: Loop monitoring selalu aktif
   - Seharusnya: Bisa dimatikan untuk manual phase control
   - Saat ini: Harus stop beat sync untuk manual control

### 💡 Improvement Opportunities (Future)

1. **Add Tempo-Only Sync Mode**
   ```javascript
   syncTempoOnly(sourceDeckId, targetDeckId) {
       // Sync tempo tapi biarkan phase manual
   }
   ```

2. **Quantize-Aware Phase Sync**
   ```javascript
   if (deck.quantizeEnabled) {
       // Gradual nudge dengan PI controller
   } else {
       // Instant seek
   }
   ```

3. **Separate Phase Sync Control**
   ```javascript
   syncPhaseOnly(sourceDeckId, targetDeckId) {
       // Hanya align phase, tidak ubah tempo
   }
   ```

---

## Kesimpulan

**Implementasi kita saat ini sudah solid untuk:**
- ✅ Tempo matching yang akurat (tidak terpengaruh Master Tempo)
- ✅ Half/double BPM handling
- ✅ Continuous phase monitoring
- ✅ Beat alignment berdasarkan beatgrid

**Untuk production-ready seperti Mixxx/Traktor/Serato, perlu:**
- Separation antara tempo sync dan phase sync
- Quantize integration
- Mode selection (full sync vs tempo only vs phase only)
- Better UI controls

**Untuk use case saat ini (basic beat sync), implementasi sudah cukup!**

---

## Referensi

- Mixxx Source Code: https://github.com/mixxxdj/mixxx
- Mixxx Wiki: https://github.com/mixxxdj/mixxx/wiki/Master-Sync
- Traktor Manual: Native Instruments Documentation
- Serato Support: https://support.serato.com/hc/en-us/articles/203056994-SYNC-with-Serato-DJ
