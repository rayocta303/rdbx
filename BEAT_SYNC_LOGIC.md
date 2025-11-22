# Beat Sync Logic - Grid Center Alignment

## Prinsip Dasar

Beat Sync menggunakan **Grid Center Alignment** - dua track disinkronkan saat garis beat dari track A dan track B bertemu tepat di titik tengah (center pointer/playhead).

## 5-Step Logic

### 1. Tentukan Center Point
**Center Point** adalah garis vertikal di tengah waveform (playhead/pointer/timeline center) yang menjadi titik acuan sinkronisasi.

```javascript
const sourceCenterPoint = sourceDeck.audio.currentTime;
const targetCenterPoint = targetDeck.audio.currentTime;
```

- `currentTime` adalah posisi playback saat tombol Beat Sync ditekan
- Ini adalah waktu audio yang terlihat di center playhead pada waveform

### 2. Deteksi Beat Grid Aktif
Sistem membaca posisi beat grid terdekat dari kedua track berdasarkan downbeat (kick drum utama).

```javascript
// Hitung waktu dari first beat
const sourceTimeFromFirstBeat = sourceCenterPoint - sourceFirstBeatOffset;
const targetTimeFromFirstBeat = targetCenterPoint - targetFirstBeatOffset;

// Cari beat terdekat (rounding ke beat terdekat)
const sourceNearestBeatNumber = Math.round(sourceTimeFromFirstBeat / sourceBeatLength);
const targetNearestBeatNumber = Math.round(targetTimeFromFirstBeat / targetBeatLength);

// Hitung waktu absolut beat terdekat
const sourceNearestBeatTime = sourceFirstBeatOffset + (sourceNearestBeatNumber * sourceBeatLength);
const targetNearestBeatTime = targetFirstBeatOffset + (targetNearestBeatNumber * targetBeatLength);
```

**Data yang digunakan**:
- `beatgridData[0].time` - First beat offset dari ANLZ file (PQTZ section)
- `beatLength = 60 / BPM` - Jarak antar beat dalam detik

### 3. Hitung Offset Beat
Offset adalah jarak antara posisi beat terdekat dengan center point.

```javascript
const sourceBeatOffsetFromCenter = sourceNearestBeatTime - sourceCenterPoint;
const targetBeatOffsetFromCenter = targetNearestBeatTime - targetCenterPoint;

const offsetDifference = sourceBeatOffsetFromCenter - targetBeatOffsetFromCenter;
```

**Formula**:
```
offset = posisi_beat_target - posisi_center
```

**Contoh**:
- Jika beat ada 50ms **sebelum** center: offset = -50ms
- Jika beat ada 30ms **setelah** center: offset = +30ms

### 4. Geser Track yang di-Sync
Track kedua (target) digeser secara timing sampai beat-nya sejajar dengan beat master di center point.

```javascript
let newTargetTime = targetCenterPoint + offsetDifference;

// Boundary check - pastikan tidak melewati durasi track
newTargetTime = Math.max(0, Math.min(newTargetTime, targetDeck.duration - 0.1));

// Apply adjustment jika signifikan (>1ms)
if (Math.abs(offsetDifference) > 0.001) {
    targetDeck.audio.currentTime = newTargetTime;
    this.updatePlayhead(targetDeckId);
}
```

**Hasil**:
```
posisi_beat_track_B == posisi_beat_track_A == center_point
```

### 5. Lock Phase
Setelah sinkron, kedua track dikunci:
- **BPM disamakan** - Sudah dilakukan di `syncBPM()` sebelum `snapBeatsToGrid()` dipanggil
- **Phase beat dipertahankan** - Beats tetap sejajar terhadap center point selama BPM sama

## Implementasi dalam Code

### File: `public/js/dual-player.js`

```javascript
snapBeatsToGrid(sourceDeckId, targetDeckId, targetBPM) {
    // ... validation checks ...

    // 1. TENTUKAN CENTER POINT
    const sourceCenterPoint = sourceDeck.audio.currentTime;
    const targetCenterPoint = targetDeck.audio.currentTime;

    // 2. DETEKSI BEAT GRID AKTIF
    const sourceNearestBeatNumber = Math.round(sourceTimeFromFirstBeat / sourceBeatLength);
    const targetNearestBeatNumber = Math.round(targetTimeFromFirstBeat / targetBeatLength);
    
    const sourceNearestBeatTime = sourceFirstBeatOffset + (sourceNearestBeatNumber * sourceBeatLength);
    const targetNearestBeatTime = targetFirstBeatOffset + (targetNearestBeatNumber * targetBeatLength);

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

    // 5. LOCK PHASE - BPM sudah di-sync sebelumnya
    // Phase tetap terjaga karena kedua track memiliki BPM yang sama
}
```

## User Flow

### Cara Menggunakan Beat Sync

1. **Load tracks** pada Deck A dan Deck B
2. **Set Master Deck** (atau akan auto-set saat sync ditekan)
3. **Play kedua tracks** (opsional, bisa juga saat pause)
4. **Klik BEAT SYNC** pada deck yang ingin di-sync
   - Deck yang diklik = Target (slave)
   - Master deck = Source (master)

### Apa yang Terjadi

1. **BPM Sync** - Pitch slider target otomatis disesuaikan agar BPM sama dengan master
2. **Beat Grid Alignment** - Target track di-shift sehingga beat-nya sejajar dengan master di center point
3. **Visual Feedback** - Notification muncul menunjukkan adjustment dalam milliseconds
4. **Console Log** - Detail lengkap tentang sync process untuk debugging

### Contoh Console Output

```
[Beat Sync - Grid Center Alignment]
  Master A: Center=45.230s | Nearest Beat=45.250s | Offset=+20ms
  Target B: Center=62.100s | Nearest Beat=62.050s (before) | Offset=-50ms
  → Adjustment: +70ms | New Target Time: 62.170s
  ✓ Beats aligned at center point | Phase locked
```

## Visual Representation

```
SEBELUM BEAT SYNC:
Deck A (Master):  -------|-----[BEAT]--------|-----
                              ↑ +20ms dari center
                         [CENTER POINT]
                         
Deck B (Target):  ----[BEAT]--|-----------|-----
                       ↑ -50ms dari center
                  [CENTER POINT]

SETELAH BEAT SYNC:
Deck A (Master):  -------|-----[BEAT]--------|-----
                              ↑
                         [CENTER POINT]
                         
Deck B (Target):  -------|-----[BEAT]--------|-----
                              ↑
                  [CENTER POINT]
                  
→ Adjustment: +70ms (geser target 70ms ke depan)
→ Kedua beats sekarang sejajar di center point
```

## Data Source

### Rekordbox Beat Grid (PQTZ Section)

Beat grid data diambil dari ANLZ files (`.DAT`, `.EXT`, `.2EX`):

```
PQTZ Tag (Big-Endian Format):
- beat: uint16 - Beat number
- tempo: uint16 - Tempo in BPM x 100
- time: uint32 - Time in milliseconds
```

**Parsing**:
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
            'tempo' => $tempo / 100,  // Convert to BPM
            'time' => $time / 1000     // Convert to seconds
        ];
        
        $pos += 8;
    }
    
    return $entries;
}
```

## Benefits

### Keunggulan Center Point Alignment

1. **Visual Consistency** - Beats sejajar dengan playhead yang terlihat
2. **Predictable Behavior** - User tahu beats akan meet di center
3. **Professional Standard** - Mirip dengan CDJ/Rekordbox beat sync
4. **Accurate Timing** - Menggunakan beat grid asli dari Rekordbox
5. **No Phase Drift** - Setelah sync, phase tetap locked selama BPM sama

### Perbedaan dengan Phase-Based Sync

**Old Method (Phase-Based)**:
- Menghitung phase fraction (0-1) dari current playback
- Adjust target berdasarkan phase difference
- Kurang intuitif karena tidak align ke visual center

**New Method (Center Point Alignment)**:
- Mencari beat terdekat ke center point
- Align kedua beats ke center point
- Visual alignment yang jelas dan predictable

## Troubleshooting

### Beat Sync Tidak Bekerja

**Cek Beat Grid Data**:
```javascript
console.log('Source Beat Grid:', sourceDeck.beatgridData);
console.log('Target Beat Grid:', targetDeck.beatgridData);
```

**Requirement**:
- Track harus di-analyze di Rekordbox
- ANLZ file harus memiliki PQTZ section
- `beatgridData` array tidak boleh kosong

### Adjustment Terlalu Besar

Jika adjustment >500ms, kemungkinan:
1. BPM detection salah
2. Beat grid offset tidak akurat
3. Track belum di-analyze dengan benar di Rekordbox

**Solution**: Re-analyze track di Rekordbox

### Beats Drift Setelah Sync

Jika beats mulai drift setelah beberapa saat:
1. BPM kedua track tidak benar-benar sama (pitch slider belum di-sync)
2. Audio engine timing issues (browser performance)
3. Track memiliki tempo changes (variable BPM)

**Solution**: 
- Gunakan BPM Sync (latching) untuk auto-sync pitch
- Re-apply Beat Sync secara berkala

## Technical Notes

### Precision

- **Time Resolution**: 1ms (0.001s threshold)
- **Beat Length Calculation**: `60 / BPM` seconds
- **Rounding**: `Math.round()` untuk nearest beat
- **Boundary Checking**: Ensure `0 ≤ newTime ≤ duration`

### Performance

- **O(1) Complexity** - Constant time calculation
- **No Iteration** - Direct beat calculation using formula
- **Minimal CPU** - Simple arithmetic operations
- **Fast Execution** - <1ms typical execution time

### Browser Compatibility

- **Web Audio API** - Semua modern browsers
- **High-Resolution Time** - `audio.currentTime` dengan ms precision
- **Canvas Rendering** - For waveform playhead visualization

---

**Version**: 2.2  
**Last Updated**: November 22, 2025  
**Implementation**: `public/js/dual-player.js` - `snapBeatsToGrid()`
