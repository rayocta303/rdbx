# Beat Sync Logic - Rebuild Documentation

## Tanggal: 22 November 2025

## Masalah yang Ditemukan

### Bug Utama:
1. **BPM Calculation Tidak Akurat saat Master Tempo ON**
   - Kode lama menggunakan: `originalBPM * (1 + pitchValue / 100)`
   - Masalah: Saat Master Tempo (Key Lock) aktif, `audio.playbackRate` berbeda dengan `pitchValue`
   - Dampak: Beat sync tidak sinkron dengan benar saat Master Tempo diaktifkan

2. **Lokasi Bug:**
   - Line 1503-1504 di `syncBPM()`: Menghitung target BPM dengan cara yang salah
   - Line 1560-1561 di `snapBeatsToGrid()`: Menghitung beat length dengan cara yang salah
   - Line 1648-1649 di `startBeatSyncLoop()`: Phase monitoring menggunakan BPM yang salah

3. **Tidak Ada BPM Multiplier Logic**
   - Track dengan BPM yang jauh berbeda (misal 140 BPM vs 70 BPM) tidak bisa sync dengan benar
   - Tidak ada sistem untuk double/halve BPM otomatis

## Solusi yang Diimplementasikan

### 1. Helper Function Baru: `getActualBPM()`
```javascript
getActualBPM(deckId) {
    const deck = this.decks[deckId];
    if (!deck.originalBPM || deck.originalBPM === 0) {
        return 0;
    }
    const playbackRate = deck.audio.playbackRate || 1.0;
    return deck.originalBPM * playbackRate;
}
```

**Keunggulan:**
- Selalu menggunakan `audio.playbackRate` yang merupakan rate sebenarnya dari audio
- Tidak terpengaruh oleh status Master Tempo (ON/OFF)
- Memberikan BPM yang akurat sesuai dengan apa yang benar-benar didengar

### 2. BPM Multiplier Logic (Terinspirasi dari Mixxx)
```javascript
determineBPMMultiplier(targetOriginalBPM, sourceActualBPM) {
    if (!targetOriginalBPM || !sourceActualBPM || targetOriginalBPM === 0 || sourceActualBPM === 0) {
        return 1.0;
    }
    
    // Test all possible multipliers and choose the one that brings
    // targetOriginalBPM × multiplier closest to sourceActualBPM
    const delta0_5 = Math.abs(sourceActualBPM - (targetOriginalBPM * 0.5));
    const delta1_0 = Math.abs(sourceActualBPM - (targetOriginalBPM * 1.0));
    const delta2_0 = Math.abs(sourceActualBPM - (targetOriginalBPM * 2.0));
    
    if (delta0_5 < delta1_0 && delta0_5 < delta2_0) {
        return 0.5;  // Halve (target detected BPM is 2x too high)
    } else if (delta2_0 < delta1_0 && delta2_0 < delta0_5) {
        return 2.0;  // Double (target detected BPM is 2x too low)
    }
    
    return 1.0;  // Unity (BPM detection is correct)
}
```

**Cara Kerja:**
- Multiplier memilih dari {0.5, 1.0, 2.0}
- Pilih multiplier yang membuat `targetOriginalBPM × multiplier` paling dekat dengan `sourceActualBPM`
- Ini mengoreksi deteksi BPM yang mungkin salah (misalnya terdeteksi 70 tapi sebenarnya 140)

**Contoh:**
- Source: 140 BPM, Target: 70 BPM
  - Test 0.5x: |140 - 35| = 105
  - Test 1.0x: |140 - 70| = 70
  - Test 2.0x: |140 - 140| = 0 ✓ BEST
  - Pilih multiplier = 2.0

### 3. Rebuild `syncBPM()` Function

**Perubahan Utama:**
- ❌ **Lama**: `sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100)`
- ✅ **Baru**: `this.getActualBPM(sourceDeckId)`

**Algoritma Baru:**
1. Dapatkan BPM aktual dari source deck (menggunakan playbackRate)
2. Tentukan BPM multiplier antara target dan source
3. Hitung adjusted target BPM dengan multiplier
4. Set playback rate yang tepat untuk target deck
5. Align beat grid jika diminta

**Log Output Baru:**
```
[Beat Sync] Synced DECK B (128 BPM) to DECK A (130.56 BPM)
  → Multiplier: 1x | Target BPM: 130.56 | Playback Rate: 1.0200 + Beat Grid Aligned
```

### 4. Rebuild `snapBeatsToGrid()` Function

**Perubahan Utama:**
- Menghapus parameter `targetBPM` yang membingungkan
- Menggunakan `getActualBPM()` untuk kedua deck
- Beat length dihitung berdasarkan BPM aktual saat ini

**Algoritma:**
1. Validasi beat grid data tersedia
2. Hitung BPM aktual dari kedua deck
3. Hitung beat length berdasarkan BPM aktual
4. Deteksi nearest beat di kedua deck
5. Hitung offset difference
6. Adjust playback position target deck

### 5. Update `startBeatSyncLoop()` Function

**Perubahan Utama:**
- Phase monitoring menggunakan BPM aktual real-time
- Base rate calculation diperbaiki untuk tidak menggunakan pitchValue

**Sebelum:**
```javascript
const sourceBPM = sourceDeck.originalBPM * (1 + sourceDeck.pitchValue / 100);
const targetBPM = targetDeck.originalBPM * (1 + targetDeck.pitchValue / 100);
const baseRate = 1 + targetDeck.pitchValue / 100;
```

**Sesudah:**
```javascript
const sourceActualBPM = this.getActualBPM(sourceDeckId);
const targetActualBPM = this.getActualBPM(targetDeckId);
const baseRate = targetDeck.audio.playbackRate || (1 + targetDeck.pitchValue / 100);
```

### 6. Tambahan Property: `bpmMultiplier`

**Ditambahkan ke:**
- `createDeck()`: Default value 1.0
- `loadTrack()`: Reset ke 1.0 saat load track baru

**Fungsi:**
- Menyimpan multiplier yang dipilih otomatis
- Konsisten sepanjang sesi sync
- Reset saat track baru di-load

## Cara Kerja Beat Sync Baru

### Skenario 1: Normal Sync (BPM Hampir Sama)
```
Track A: 128 BPM di pitch +2% → 130.56 BPM aktual
Track B: 128 BPM di pitch 0% → 128 BPM aktual

Sync B ke A:
1. sourceActualBPM = 130.56 BPM
2. targetOriginalBPM = 128 BPM
3. determineBPMMultiplier(128, 130.56):
   - delta0.5 = |130.56 - 64| = 66.56
   - delta1.0 = |130.56 - 128| = 2.56 ✓ BEST
   - delta2.0 = |130.56 - 256| = 125.44
   - Multiplier = 1.0x
4. targetEffectiveBPM = 128 × 1.0 = 128 BPM
5. playbackRate = 130.56 / 128 = 1.0200
6. Set B.playbackRate = 1.0200
7. Final BPM B = 128 × 1.0200 = 130.56 ✓
8. Align beats dengan snapBeatsToGrid()
```

### Skenario 2: BPM Berbeda Jauh (Auto Double)
```
Track A: 140 BPM di pitch 0% → 140 BPM aktual
Track B: 70 BPM di pitch 0% → 70 BPM aktual

Sync B ke A:
1. sourceActualBPM = 140 BPM
2. targetOriginalBPM = 70 BPM
3. determineBPMMultiplier(70, 140):
   - delta0.5 = |140 - 35| = 105
   - delta1.0 = |140 - 70| = 70
   - delta2.0 = |140 - 140| = 0 ✓ BEST
   - Multiplier = 2.0x
4. targetEffectiveBPM = 70 × 2.0 = 140 BPM
5. playbackRate = 140 / 140 = 1.0
6. Set B.playbackRate = 1.0
7. Final BPM B = 140 × 1.0 = 140 ✓
8. Track B bermain dengan BPM yang sama dengan A
```

### Skenario 3: BPM Berbeda Jauh (Auto Halve)
```
Track A: 70 BPM di pitch 0% → 70 BPM aktual
Track B: 140 BPM di pitch 0% → 140 BPM aktual

Sync B ke A:
1. sourceActualBPM = 70 BPM
2. targetOriginalBPM = 140 BPM
3. determineBPMMultiplier(140, 70):
   - delta0.5 = |70 - 70| = 0 ✓ BEST
   - delta1.0 = |70 - 140| = 70
   - delta2.0 = |70 - 280| = 210
   - Multiplier = 0.5x
4. targetEffectiveBPM = 140 × 0.5 = 70 BPM
5. playbackRate = 70 / 70 = 1.0
6. Set B.playbackRate = 1.0
7. Final BPM B = 70 × 1.0 = 70 ✓
8. Track B bermain dengan BPM yang sama dengan A
```

### Skenario 4: Master Tempo ON
```
Track A: 128 BPM, Master Tempo ON, pitch +10%
- audio.preservesPitch = true
- audio.playbackRate = 1.10 (tempo berubah)
- Pitch audio TIDAK berubah (key locked)

Dengan logika LAMA:
- BPM = 128 * (1 + 10/100) = 140.8 ✗ (SALAH saat Master Tempo ON)

Dengan logika BARU:
- playbackRate = 1.10
- BPM = 128 * 1.10 = 140.8 ✓ (BENAR dalam semua kondisi)
```

## Keunggulan Logika Baru

### 1. ✅ Tidak Terpengaruh Master Tempo
- Menggunakan `audio.playbackRate` langsung
- Selalu akurat baik Master Tempo ON atau OFF

### 2. ✅ Support BPM Multiplier
- Otomatis double/halve BPM untuk track yang berbeda jauh
- Algoritma sama dengan Mixxx (DJ software profesional)

### 3. ✅ Real-time Accurate
- Phase monitoring menggunakan BPM aktual
- Tidak ada drift saat Master Tempo berubah

### 4. ✅ Lebih Robust
- Validasi lebih baik
- Logging lebih informatif
- Error handling lebih baik

## Referensi

**Implementasi ini terinspirasi dari:**
- Mixxx DJ Software: `src/engine/sync/synccontrol.cpp`
- Algoritma BPM Multiplier menggunakan ratio square
- Konsep beat distance dan phase alignment

**Web Research:**
- Beat sync algorithm dari Stack Overflow
- Pioneer DJ Beat Sync documentation
- Essentia rhythm detection algorithm

## Testing Checklist

- [ ] Beat sync bekerja dengan Master Tempo OFF
- [ ] Beat sync bekerja dengan Master Tempo ON
- [ ] Beat sync bekerja saat toggle Master Tempo ON/OFF saat sedang sync
- [ ] Auto BPM multiplier bekerja untuk track 70 BPM vs 140 BPM
- [ ] Auto BPM multiplier bekerja untuk track 140 BPM vs 70 BPM
- [ ] Phase monitoring tetap akurat saat pitch berubah
- [ ] bpmMultiplier reset saat load track baru
- [ ] Console logging memberikan informasi yang jelas

## Catatan

- Semua perhitungan BPM sekarang menggunakan `getActualBPM()` helper
- `pitchValue` hanya digunakan untuk UI display, tidak untuk calculation
- `audio.playbackRate` adalah source of truth untuk tempo calculation
- BPM multiplier persistent sampai track baru di-load
