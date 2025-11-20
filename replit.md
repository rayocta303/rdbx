# Rekordbox Export Reader - PHP Edition

## Overview
Tool PHP untuk membaca dan menampilkan database Rekordbox USB/SD export dengan web GUI modern. Project ini mengkonversi implementasi Python menjadi PHP murni dengan struktur modular.

## Recent Changes
- **2025-11-20**: Implementasi Dual DJ Player dengan BPM Sync
  - Dual deck player dengan full DJ controls:
    - Play/Pause functionality per deck
    - Hot cue pads (8 pads per deck) dengan trigger functionality
    - Waveform visualization dengan beatgrid overlay
    - Zoom controls (1x-64x, default 16x) dengan drag support
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
- ✅ BPM Pitch control dengan Master Tempo toggle
- ✅ BPM Sync functionality antar deck
- ✅ Zoom/drag controls untuk waveform (1x-64x)
- ✅ Center playhead dengan scrolling waveform
- ✅ Integrated sync buttons dalam deck headers

## References
- Deep Symmetry crate-digger: https://github.com/Deep-Symmetry/crate-digger
- Kaitai Struct spec: rekordbox_pdb.ksy
- Documentation: https://djl-analysis.deepsymmetry.org/
