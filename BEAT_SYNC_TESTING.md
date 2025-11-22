# Beat Sync - Testing & Debugging Guide

## Tanggal: 22 November 2025

Dokumen ini menjelaskan cara testing beat sync yang sudah diperbaiki dan cara membaca console logs untuk debugging.

---

## Bug Yang Sudah Diperbaiki ✅

### Bug #1: Multiplier Calculation Menggunakan BPM Yang Salah (CRITICAL)

**Masalah:**
- `determineBPMMultiplier()` menerima `sourceActualBPM` yang sudah include multiplier
- Saat source punya multiplier 2.0x, `sourceActualBPM = 140` (dari 70 original)
- Saat target sync ke source, multiplier calculated dari BPM yang sudah doubled
- Result: cascading errors pada chained syncs

**Fix:**
```javascript
// NEW: getTrueBPM() untuk isolate true measured tempo
getTrueBPM(deckId) {
    const playbackRate = deck.audio.playbackRate || 1.0;
    return deck.originalBPM * playbackRate;  // Tanpa multiplier
}

// UPDATED: determineBPMMultiplier() menggunakan true BPM
determineBPMMultiplier(targetOriginalBPM, sourceTrueBPM) {
    // Logic pilih 0.5, 1.0, atau 2.0 berdasarkan true BPM
}
```

### Bug #2: Pitch Tidak Di-Reset Saat Re-Sync (CRITICAL)

**Masalah:**
- Deck B sync ke Deck A → playbackRate = 2.0
- Deck B sync ke Deck C → playbackRate baru calculated tapi tidak reset dulu
- Result: playbackRate accumulates (2.0 × new rate = wrong tempo)

**Fix:**
```javascript
syncBPM(sourceDeckId, targetDeckId, snapBeats = false) {
    // 1. Reset pitch to 0 FIRST
    slider.value = 0;
    this.setPitch(targetDeckId, 0);
    
    // 2. Set new multiplier
    targetDeck.bpmMultiplier = newMultiplier;
    
    // 3. Calculate fresh playbackRate
    const requiredPlaybackRate = sourceActualBPM / targetEffectiveBPM;
    
    // 4. Apply new pitch
    this.setPitch(targetDeckId, requiredPitchPercent);
}
```

---

## Test Scenarios

### Persiapan Testing

1. Buka browser dev tools (F12)
2. Switch ke tab Console
3. Load 2 tracks dengan BPM berbeda:
   - Track A: 128-140 BPM (recommended)
   - Track B: 70 BPM atau 140 BPM (untuk test multiplier)
4. Play kedua tracks
5. Test beat sync dengan klik tombol SYNC

### Scenario 1: Normal Sync (BPM Hampir Sama)

**Setup:**
- Deck A: 128 BPM, pitch +2% → 130.56 BPM actual
- Deck B: 130 BPM, pitch 0% → 130 BPM actual

**Action:**
1. Play Deck A
2. Play Deck B
3. Klik SYNC B→A (sync Deck B ke Deck A)

**Expected Result:**
```
[Beat Sync] Synced DECK B (130 BPM) to DECK A
  Source True BPM: 130.56 | Source Effective BPM: 130.56
  → Multiplier: 1.0x | Target Effective: 130.00 BPM
  → Final BPM: 130.56 | Playback Rate: 1.0043
```

**Verify:**
- ✅ Multiplier = 1.0 (no double/half)
- ✅ Playback Rate ≈ 1.0 (close to unity)
- ✅ Final BPM matches Source BPM
- ✅ Beats aligned (visual check pada waveform)

---

### Scenario 2: Half/Double BPM (Auto Multiplier)

**Setup:**
- Deck A: 140 BPM, pitch 0% → 140 BPM actual
- Deck B: 70 BPM, pitch 0% → 70 BPM actual

**Action:**
1. Play Deck A (140 BPM)
2. Play Deck B (70 BPM)
3. Klik SYNC B→A

**Expected Result:**
```
[Beat Sync] Synced DECK B (70 BPM) to DECK A
  Source True BPM: 140.00 | Source Effective BPM: 140.00
  → Multiplier: 2.0x | Target Effective: 140.00 BPM
  → Final BPM: 140.00 | Playback Rate: 1.0000
```

**Verify:**
- ✅ Multiplier = 2.0 (auto-detected half BPM)
- ✅ Playback Rate = 1.0 (perfect match)
- ✅ Final BPM = 140 (matches source)
- ✅ Deck B sounds 2x faster (check audio)

**Reverse Test:**
- Klik SYNC A→B (sync Deck A ke Deck B)

**Expected Result:**
```
[Beat Sync] Synced DECK A (140 BPM) to DECK B
  Source True BPM: 140.00 | Source Effective BPM: 140.00
  → Multiplier: 0.5x | Target Effective: 70.00 BPM
  → Final BPM: 70.00 | Playback Rate: 2.0000
```

**Verify:**
- ✅ Multiplier = 0.5 (auto-detected double BPM)
- ✅ Playback Rate = 2.0 (needs 2x speed to match 70→140)
- ✅ Final BPM = 70 (matches source - Deck B which is at effective 140 due to 2.0x multiplier)

---

### Scenario 3: Chained Transitions (CRITICAL TEST)

**Setup:**
- Deck A: 140 BPM
- Deck B: 70 BPM
- Deck C: 100 BPM (load later)

**Action 1: B→A**
1. Sync B ke A
2. Check console: Multiplier = 2.0, PlaybackRate ≈ 1.0

**Action 2: Load Deck C (100 BPM)**
1. Stop Deck A atau Deck B
2. Load track 100 BPM ke deck yang stop
3. Play deck tersebut

**Action 3: B→C (Critical!)**
1. Sync B ke C
2. Check console logs

**Expected Result:**
```
[Beat Sync] Synced DECK B (70 BPM) to DECK C
  Source True BPM: 100.00 | Source Effective BPM: 100.00
  → Multiplier: 1.0x | Target Effective: 70.00 BPM
  → Final BPM: 100.00 | Playback Rate: 1.4286
```

**Verify:**
- ✅ Multiplier = 1.0 (not 2.0 dari sync sebelumnya!)
- ✅ Playback Rate = 1.4286 (100/70 = 1.428)
- ✅ No cascading error (playbackRate tidak jadi 2.8+)
- ✅ Deck B tempo match Deck C

**Why This Is Critical:**
- Old bug: Multiplier stuck at 2.0 → targetEffective = 140 → playbackRate = 100/140 = 0.71 (WRONG)
- With fix: Multiplier recalculates to 1.0 → targetEffective = 70 → playbackRate = 100/70 = 1.43 (CORRECT)

---

### Scenario 4: Master Tempo ON/OFF

**Setup:**
- Deck A: 128 BPM, Master Tempo ON, Pitch +10%

**Action:**
1. Set Deck A pitch to +10% dengan Master Tempo ON
2. Sync Deck B ke Deck A

**Expected Result:**
- Source True BPM: 140.80 (128 × 1.10)
- Master Tempo status tidak affect sync calculation
- Playback rate calculated correctly dari true BPM

---

## Reading Console Logs

### Beat Sync Logs

Saat sync, cari log ini:

```
[Beat Sync] Synced DECK X (OriginalBPM) to DECK Y
  Source True BPM: XXX.XX | Source Effective BPM: XXX.XX
  → Multiplier: X.Xx | Target Effective: XXX.XX BPM
  → Final BPM: XXX.XX | Playback Rate: X.XXXX + Beat Grid Aligned
```

**Key Metrics:**
- **Source True BPM**: originalBPM × playbackRate (tanpa multiplier)
- **Source Effective BPM**: originalBPM × multiplier × playbackRate (dengan multiplier)
- **Multiplier**: 0.5, 1.0, atau 2.0
- **Target Effective**: targetOriginalBPM × multiplier
- **Final BPM**: hasil akhir BPM target setelah sync
- **Playback Rate**: rate yang diapply ke audio element

### Beat Sync PI Loop Logs

Saat locked sync aktif:

```
[Beat Sync PI] Error: X.Xms | Filtered: X.Xms | Integral: X.Xms | Rate Δ: X.XXX%
```

**What To Look For:**
- ✅ **GOOD**: Error ±5ms, Rate Δ <0.1% → sync stable
- ⚠️ **WARNING**: Error ±10-20ms, Rate Δ 0.1-0.2% → minor drift
- ❌ **BAD**: Error >30ms, frequent slip corrections → sync unstable

**Slip Correction:**
```
[Beat Sync PI] Slip correction: X.Xms → Jump X.Xms | Reset PI state
```
- Occasional slip OK (when you change pitch manual)
- Frequent slip (every few seconds) = masalah

---

## Common Issues & Solutions

### Issue 1: "Sync loncat-loncat tidak match"

**Symptoms:**
- Beats drift out of sync setelah beberapa detik
- Playback rate terus berubah-ubah
- Console show frequent slip corrections

**Debug:**
1. Check multiplier value di console log
2. Verify playbackRate ≈ 1.0 setelah sync (jika BPM similar)
3. Check apakah beatgrid ada (requires Rekordbox analysis)

**Solution:**
- Jika multiplier salah (stuck at 2.0 padahal seharusnya 1.0): Bug sudah fixed, refresh browser
- Jika beatgrid missing: Analyze track di Rekordbox dulu
- Jika PI loop unstable: Might need tuning (current Kp=0.035, Ki=0.015)

---

### Issue 2: "Playback Rate terlalu besar/kecil"

**Symptoms:**
- Playback Rate > 2.0 atau < 0.5
- Audio sound chipmunk (too fast) atau slurred (too slow)

**Debug:**
1. Check multiplier calculation
2. Verify source true BPM vs effective BPM
3. Check if pitch was reset before sync

**Solution:**
- Bug sudah fixed dengan getTrueBPM() separation
- Refresh browser untuk apply fix

---

### Issue 3: "Chained sync cascading errors"

**Symptoms:**
- A→B works fine
- B→C results in wrong tempo (2x atau 0.5x dari expected)

**Debug:**
1. Check console: "Source True BPM" vs "Source Effective BPM"
2. Verify multiplier recalculates (tidak stuck di value lama)
3. Check pitch reset before new sync

**Solution:**
- Bug sudah fixed dengan pitch reset
- Refresh browser

---

## Summary - What Was Fixed

### Before Fix ❌

```javascript
// Multiplier menggunakan BPM yang sudah include multiplier
const sourceActualBPM = this.getActualBPM(sourceDeckId); // ← Include multiplier!
targetDeck.bpmMultiplier = this.determineBPMMultiplier(
    targetOriginalBPM, 
    sourceActualBPM  // ← WRONG: Already doubled/halved
);

// Pitch tidak di-reset
if (!targetDeck.bpmMultiplier) {  // ← Only set once
    targetDeck.bpmMultiplier = ...;
}
```

**Result:** Cascading errors, chained syncs fail

### After Fix ✅

```javascript
// Multiplier menggunakan TRUE BPM (tanpa multiplier)
const sourceTrueBPM = this.getTrueBPM(sourceDeckId); // ← No multiplier!
const sourceActualBPM = this.getActualBPM(sourceDeckId); // ← With multiplier

// Reset pitch FIRST
slider.value = 0;
this.setPitch(targetDeckId, 0);

// Always recalculate multiplier
targetDeck.bpmMultiplier = this.determineBPMMultiplier(
    targetOriginalBPM,
    sourceTrueBPM  // ← CORRECT: True measured tempo
);

// Calculate fresh playbackRate
const requiredPlaybackRate = sourceActualBPM / targetEffectiveBPM;
```

**Result:** Stable sync, chained transitions work correctly

---

## Next Steps

Jika masih ada masalah setelah fix ini:

1. **Check browser cache**: Hard refresh (Ctrl+Shift+R)
2. **Verify logs**: Pastikan console log show format baru
3. **Test systematically**: Follow scenario 1→2→3 step by step
4. **Report specifics**: Share console logs untuk specific case yang fail

---

## Technical Details

### Function Roles

| Function | Returns | Purpose |
|----------|---------|---------|
| `getTrueBPM()` | originalBPM × playbackRate | True measured tempo, untuk multiplier calculation |
| `getActualBPM()` | originalBPM × multiplier × playbackRate | Final effective BPM yang terdengar |
| `determineBPMMultiplier()` | 0.5, 1.0, atau 2.0 | Pilih multiplier based on true BPM comparison |

### Sync Flow

1. Get `sourceTrueBPM` (no multiplier) → untuk determine multiplier
2. Get `sourceActualBPM` (with multiplier) → untuk target tempo
3. Reset target pitch to 0 → clear old adjustments
4. Set new multiplier → based on sourceTrueBPM
5. Calculate playbackRate → sourceActual / targetEffective
6. Apply pitch → set new playbackRate
7. Snap beats → align phase

---

**Testing Status**: ✅ Ready for user testing
**Last Updated**: 22 November 2025
