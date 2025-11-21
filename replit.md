# Rekordbox Export Reader - PHP Edition

## Overview
Tool PHP untuk membaca dan menampilkan database Rekordbox USB/SD export dengan web GUI modern. Project ini mengkonversi implementasi Python menjadi PHP murni dengan struktur modular.

## Recent Changes
- **2025-11-21 (Latest)**: Beat Grid Pitch Scaling & Multi-File ANLZ Parsing
  - **Beat Grid Pitch Scaling Fix** (Critical):
    - Fixed beat grid tidak stretch/compress saat pitch slider berubah
    - Removed incorrect `beat.time / pitchMultiplier` calculation dari renderBeatgrid()
    - Beat timestamps (PQTZ) adalah absolute time, tidak perlu di-scale
    - viewDuration (via effectiveZoom) already handles stretching otomatis
    - Beat grid sekarang stretch/compress in lockstep dengan waveform saat tempo changes
  - **Multi-File ANLZ Parsing Enhancement**:
    - Implemented parsing untuk ALL ANLZ file types (.DAT, .EXT, .2EX)
    - .DAT files: PQTZ beat grid data + basic waveform
    - .EXT files: PWV5 detailed color waveform (preferred quality)
    - Data merging strategy: beat grid dari DAT, waveform prefer EXT over DAT
    - Results: 216 beats loaded untuk Track #2, 398 beats untuk Track #1
  - **Path Normalization Fix**:
    - Convert Windows backslashes ke forward slashes sebelum concatenate dengan exportPath
    - Linux compatibility untuk ANLZ file discovery
    - Fixed "ANLZ files not found" error pada Linux systems
  - **Waveform Time-Stretching**:
    - Added pitchMultiplier ke effectiveZoom calculation
    - Waveform visual stretch/compress saat pitch slider berubah
    - Re-render waveform + cue markers di setPitch()
    - Consistent visual feedback untuk tempo adjustments
  - **Drag Interaction Fix**:
    - mousedown handler sekarang calculate effectiveZoom dengan pitchMultiplier
    - visibleDuration consistent dengan renderWaveform()
    - Fixes cursor jump/desync saat pitch adjusted

- **2025-11-21 (sebelumnya)**: Critical Bug Fixes - High-DPI Interaction & Beat Sync
  - **High-DPI Waveform Interaction Fix** (Critical):
    - Fixed waveform scrubbing accuracy pada high-DPI displays (Retina, 4K)
    - Changed pixelsPerSecond calculation dari `canvas.width` (physical pixels) ke `container.clientWidth` (CSS pixels)
    - Resolves issue dimana waveform scrubbing berjalan di half-speed atau incorrect speed pada DPR=2/3 displays
    - Maintains accurate cursor-to-time mapping across semua display densities
  - **Beat Sync Data Structure Fix** (Critical):
    - Fixed beat grid data structure mismatch antara AnlzParser dan dual-player.js
    - Changed dari `beatgridData.beats[0]` ke `beatgridData[0]` (direct array access)
    - Added `Array.isArray()` validation di semua beat grid accessors
    - Resolves "Beat grid not available" error message untuk tracks dengan valid PQTZ data
    - Beat Sync dan Quantize functionality sekarang bekerja correctly dengan Rekordbox beat grid data

- **2025-11-21 (sebelumnya)**: Enhanced Waveform Rendering & BPM Sync Improvements
  - **Rounded Waveform Caps**: Rekordbox-style smooth waveform bars
    - Implemented `ctx.roundRect()` dengan fallback ke manual arc paths
    - Rounded bar caps untuk professional appearance seperti referensi Rekordbox
    - Dynamic bar radius calculation untuk consistency di semua zoom levels
  - **MIXXX-Style Max Sampling**: Pixel-based rendering untuk smooth, non-pixelated waveforms
    - Iterate per display pixel (bukan per sample) untuk optimal performance
    - Max amplitude aggregation per pixel window untuk anti-aliasing
    - High DPI support dengan devicePixelRatio scaling
  - **BPM Sync Toggle**: Converted dari momentary ke latching toggle
    - Toggle on/off seperti Quantize button (bukan momentary push)
    - Auto-sync pitch slider saat BPM Sync active - perubahan di satu deck langsung sync ke deck lain
    - Auto-enable master deck saat sync pressed jika master belum set (hanya jika track loaded)
  - **In-UI Notifications**: Replaced semua JavaScript `alert()` calls dengan `showNotification()`
    - Better UX dengan non-blocking toast-style notifications
    - Consistent notification system di seluruh aplikasi
  - **PQTZ Beat Grid Parsing**: Implemented correct big-endian parsing di AnlzParser.php
    - Parse beat grid data untuk accurate BPM sync dan quantize
    - Supports multiple beats per PQTZ tag dengan proper offset calculation

- **2025-11-21 (sebelumnya)**: Master Deck Selection & Cross-Browser Playback Fix
  - **Chrome Playback Fix**: Resolved AudioContext autoplay policy issue
    - Added async user interaction handler untuk resume AudioContext
    - Implemented di togglePlay() dengan proper error handling
    - Works reliably di Chrome, Firefox, Safari, Edge
  - **Master Deck Selection Feature** (Rekordbox-style):
    - MASTER button pada setiap deck dengan gold styling ketika active
    - Push/Pull sync logic:
      - Clicking sync on master deck → pushes settings to other deck
      - Clicking sync on non-master deck → syncs this deck to master
    - Track validation: alerts jika target deck tidak ada track loaded
    - Prevents self-sync dengan clear user feedback
  - **Enhanced Waveform Rendering**:
    - Anti-aliasing untuk smooth waveform curves (imageSmoothingEnabled)
    - Gradient-based waveform fills untuk depth dan visual appeal
    - Enhanced shadows dan visual effects
    - Professional appearance seperti Mixxx/Rekordbox/Serato
  - **Beat Sync Improvements**:
    - Validation untuk beat grid data sebelum snap
    - Clear error messages jika beat grid tidak tersedia
    - Early return dengan user alerts untuk better UX

- **2025-11-21 (sebelumnya)**: Perbaikan Waveform Synchronization & Enhancements
  - **Fixed Waveform Pointer Sync**: Mengatasi bug sinkronisasi pointer saat zoom in/out
    - Semua referensi deck.zoomLevel diganti dengan this.sharedZoomLevel
    - Konsisten di renderWaveform(), renderCueMarkers(), dan semua fungsi terkait
  - **Updated Zoom Range**: Zoom levels sekarang [16x, 32x, 64x, 128x] (min 16x, max 128x)
  - **Enhanced Beat Grid**: Meningkatkan visibilitas beat grid
    - Opacity ditingkatkan dari 0.3 ke 0.8 untuk kontras putih yang lebih jelas
    - Line width ditingkatkan dari 2 ke 4 untuk tampilan lebih tebal/bold
  - **Reverse Audio Scratching**: Implementasi fitur scratching mundur
    - Audio playback terbalik saat swipe/drag ke belakang
    - Menggunakan manual reverse loop dengan requestAnimationFrame
    - Konsisten speed limits 0.25-4x untuk forward dan reverse
    - Sinkronisasi waveform dengan audio via updatePlayhead()
  - **Fixed Drag Direction**: Center-based calculation untuk drag behavior intuitif
    - Drag kanan = scroll mundur, drag kiri = scroll maju
    - Audio transport selalu sinkron dengan waveform pointer
    - Scratch direction derived dari centerDelta untuk konsistensi

- **2025-11-20**: Implementasi Complete DJ Player Features
  - **Tempo Nudge**: Temporary speed adjustment (±4%) untuk beat matching
  - **Volume Control**: Independent volume slider per deck (0-100%)
  - **Beat Sync**: BPM sync + beat grid phase alignment untuk perfect beat matching
    - Uses actual Rekordbox beat grid offsets
    - Calculates phase difference relative to first beat
    - Snaps target deck to source deck's beat phase
  - **Quantize**: Snap to nearest beat functionality
    - Toggle per deck
    - Applies to hot cue triggers
    - Uses Rekordbox beat grid for accuracy
  - Fixed browser cache dengan timestamp parameter
  - Integrated sync buttons dalam deck headers

- **2025-11-20**: Implementasi Dual DJ Player dengan BPM Sync
  - Dual deck player dengan full DJ controls:
    - Play/Pause functionality per deck
    - Hot cue pads (8 pads per deck) dengan trigger functionality
    - Waveform visualization dengan beatgrid overlay
    - Zoom controls (16x-128x, default 16x) dengan drag support dan scratching
    - Center playhead dengan scrolling waveform
    - BPM Pitch control (-16% to +16%) dengan Master Tempo toggle
    - BPM Sync buttons integrated dalam deck headers
  - Sync buttons dipindahkan ke dalam player component untuk UI yang lebih rapi
  - Deck A: Tombol "→ B" untuk sync tempo ke Deck B
  - Deck B: Tombol "→ A" untuk sync tempo ke Deck A
  - Fixed JavaScript syntax errors di dual-player.js

- **2025-11-20 (sebelumnya)**: Modularisasi frontend public/index.php
  - Refactor file monolitik menjadi struktur modular terorganisir:
    - `public/css/main.css`: Semua CSS styles terpisah dari HTML
    - `public/partials/head.php`: HTML head section dengan meta tags dan asset links
    - `public/partials/footer.php`: JavaScript includes dan closing HTML tags
    - `public/components/stats.php`: Komponen statistik database
    - `public/components/browser.php`: Komponen library browser dan track listing
    - `public/components/debug.php`: Komponen debug metadata
  - File index.php sekarang hanya 58 baris (dari 700+ baris)
  - Semua functionality terjaga, tidak ada breaking changes
  - Lebih mudah untuk maintenance dan development

- **2025-11-20 (sebelumnya)**: Implementasi lengkap ANLZ parser dan frontend cue/waveform display
  - Fixed GenreParser untuk correct genre extraction (Track 1: "Indonesian Bounce" ✓)
  - Upgraded Track ID dari 16-bit ke 32-bit untuk ANLZ file mapping
  - Implementasi complete AnlzParser dengan support untuk:
    - Cue points (PCO2/PCOB): memory cues, hot cues, loops
    - Waveform (PWAV/PWV5): preview, detail, color waveform
  - Frontend updates:
    - Added "Cues" column untuk menampilkan jumlah cue points
    - Click-to-view modal dengan detail track info
    - Canvas-based waveform visualization
    - Cue points display dengan timing dan type info
  - Known issues: Track 2 title dan key parsing memerlukan investigation lebih lanjut terhadap PDB string encoding

- **2025-11-20 (awal)**: Konversi dari Python ke PHP
  - PdbParser untuk membaca format DeviceSQL
  - TrackParser untuk ekstraksi metadata track
  - PlaylistParser dengan corruption detection
  - Web GUI dengan Tailwind CDN (no build tools)

## Project Architecture

### Core Parsers (src/Parsers/)
- **PdbParser.php**: Membaca export.pdb (DeviceSQL format, little-endian)
- **TrackParser.php**: Parse track rows (title, artist, BPM, key, dll)
- **PlaylistParser.php**: Parse playlist tree dengan corruption handling
- **AnlzParser.php**: Parse ANLZ files (beatgrid, waveform, cue points, big-endian)

### Main Components
- **RekordboxReader.php**: Orchestrator yang mengkoordinasikan semua parser
- **Logger.php**: Logging system dengan corrupt playlist tracking

### Frontend Structure (public/)
- **index.php**: Entry point utama, load data dan orchestrate components (58 lines)
- **partials/head.php**: HTML head section, meta tags, CSS/JS links
- **partials/footer.php**: JavaScript logic dan closing HTML tags
- **components/stats.php**: Statistics display component
- **components/browser.php**: Library browser, playlist tree, track listing
- **components/player.php**: Dual deck DJ player dengan sync buttons integrated
- **components/debug.php**: Database metadata debug panel
- **css/main.css**: All custom CSS styles
- **js/**: JavaScript modules
  - **dual-player.js**: Main orchestrator untuk dual deck player (626 lines)
  - **audio-player.js**: Web Audio API wrapper untuk playback
  - **waveform-renderer.js**: Canvas-based waveform dengan beatgrid overlay
  - **cue-manager.js**: Hot cue triggers dan markers
  - **track-detail.js**: Track detail modal display

### Tech Stack
- PHP 8.2 (no frameworks)
- Tailwind CSS via CDN
- PHP built-in server (development)
- Modular structure untuk easy maintenance

## User Preferences
- Menggunakan PHP murni tanpa framework
- Struktur modular dan terorganisir
- Tailwind CDN untuk CSS (no build tools)
- Bahasa Indonesia untuk dokumentasi

## Database Format Details

### PDB Structure
- Page size: 4096 bytes (fixed)
- Little-endian byte order
- DeviceSQL string format (ASCII, UTF-16LE)
- Row-based tables dengan page heap allocation

### ANLZ Structure  
- Big-endian byte order (berbeda dari PDB!)
- Tagged sections (PQTZ, PCOB, PWAV, dll)
- Multiple file types: .DAT, .EXT, .2EX

## Known Issues
- **Track 2 Title Truncation**: Title menampilkan "eHardwell -" alih-alih "Hardwell - Spaceman (Reyputra & Jayjax Edit) TRIM"
  - Root cause: PDB string encoding complexity dengan flag 0x03 dan multi-byte encoding
  - Requires deeper investigation of DeviceSQL string format variations
- **Track 2 Key Mapping**: Key menampilkan "9A" (key_id=1) seharusnya "2A" (key_id=2)
  - Need to map correct analyzed key field in track row structure
- **ANLZ File Mapping**: Cue points dan waveform belum ter-load karena ANLZ filename tidak match dengan track IDs
  - Track IDs: 1, 2
  - ANLZ folders: P044/00015948 (88392), P03F/000272DD (2568925)
  - Requires investigation of correct ANLZ-to-track mapping mechanism

## Completed Features
- ✅ Genre parsing dengan fallback untuk empty pages
- ✅ Track metadata parsing (title, artist, genre, BPM, key)
- ✅ Playlist parsing dan display
- ✅ ANLZ Parser implementation untuk cue points dan waveform
- ✅ Frontend modal dengan detail view
- ✅ Waveform canvas visualization dengan beatgrid overlay
- ✅ 32-bit track ID support
- ✅ Dual deck DJ player dengan professional controls
- ✅ Hot cue pads (8 per deck) dengan trigger functionality
- ✅ BPM Pitch control (-16% to +16%) dengan Master Tempo toggle
- ✅ Tempo Nudge buttons untuk temporary speed adjustment
- ✅ Independent volume control per deck
- ✅ BPM Sync functionality (tempo only)
- ✅ Beat Sync functionality (tempo + beat grid phase alignment)
- ✅ Quantize toggle dengan beat grid snap
- ✅ Zoom/drag controls untuk waveform (1x-64x)
- ✅ Center playhead dengan scrolling waveform
- ✅ Integrated sync/beat sync buttons dalam deck headers

## References
- Deep Symmetry crate-digger: https://github.com/Deep-Symmetry/crate-digger
- Kaitai Struct spec: rekordbox_pdb.ksy
- Documentation: https://djl-analysis.deepsymmetry.org/
