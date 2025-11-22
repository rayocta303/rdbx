# Rekordbox Export Reader - MIXXX Edition

Professional DJ Library Manager dengan Dual Deck Player untuk membaca dan memainkan Rekordbox USB exports dengan Web GUI bergaya MIXXX DJ Software.

## ✨ Fitur Utama

### 🎛️ Professional Dual DJ Player
- **Independent Dual Decks**: Load dan play tracks pada Deck A dan Deck B secara bersamaan
- **BPM Pitch Control**: ±16% tempo adjustment dengan real-time BPM display
- **Master Tempo (Key Lock)**: Maintain pitch asli saat adjust tempo
- **Tempo Nudge**: Temporary speed adjustment (±4%) untuk beat matching
- **Volume Control**: Independent volume slider per deck (0-100%)
- **BPM Sync (Latching Toggle)**:
  - Toggle on/off mode (seperti Quantize, bukan momentary button)
  - Saat aktif, pitch slider master deck otomatis sync ke slave deck
  - Auto-enable master deck jika belum di-set (saat kedua deck memiliki track)
  - Active state indication dengan highlight button
- **Beat Sync (Grid Center Alignment)**: Sync tempo + snap beat grid phase untuk perfect alignment
  - **Center Point Alignment**: Beats dari kedua track disinkronkan ke center point (playhead)
  - **5-Step Logic**:
    1. Tentukan Center Point - Posisi playback saat ini (center pointer)
    2. Deteksi Beat Grid Aktif - Cari beat terdekat ke center point pada kedua track
    3. Hitung Offset Beat - `offset = posisi_beat_target - posisi_center`
    4. Geser Track yang di-Sync - Align beats ke center point
    5. Lock Phase - BPM disamakan, phase beat dipertahankan sejajar
  - Uses actual Rekordbox beat grid offsets (PQTZ section dari ANLZ files)
  - Momentary button untuk one-time sync dengan visual feedback
- **Quantize**: Snap to nearest beat functionality
  - Toggle per deck
  - Applies to hot cue triggers
  - Uses Rekordbox beat grid untuk accuracy

### 🎨 MIXXX-Inspired Professional UI
- **Dark Theme**: Interface profesional bergaya MIXXX LateNight Skin
- **FontAwesome Icons**: Modern icon system menggantikan emoji
- **Dual Deck Layout**: Library browser, dual waveform display, dan hot cue pads
- **Color-Coded Waveforms**: Visualisasi waveform dengan analisis frekuensi RGB
- **Smooth Waveform Rendering**: 
  - High DPI canvas dengan devicePixelRatio support untuk retina displays
  - Anti-aliasing dan sub-pixel rendering untuk smooth edges seperti Rekordbox
  - Optimized glow effects dan gradients
- **Hot Cue Pads**: 8 pads per deck dengan color coding dan gradient effects
- **Real-time Waveform Rendering**: Canvas-based waveform dengan beatgrid overlay
- **Center Playhead**: Fixed playhead dengan auto-scrolling waveform
- **Zoom Controls**: 1x to 64x zoom (default 16x) dengan drag-to-seek
- **In-UI Notifications**: Toast notifications untuk feedback (menggantikan JavaScript alerts)

### 📀 Parsing Database Lengkap
- Membaca file `export.pdb` dan `exportExt.pdb` (DeviceSQL format)
- Ekstraksi track metadata (title, artist, album, BPM, key, duration)
- Parsing playlist structure dan folder hierarchy
- **ANLZ File Integration**: Parse waveform dan cue points dari `USBANLZ/*.DAT/*.EXT/*.2EX`
- Audio streaming dengan byte-range support untuk playback
- Corruption detection dan graceful error handling

### 🎵 Audio & Analysis Features
- **Waveform Visualization**: 
  - Color-coded frequency data (RGB dari ANLZ files)
  - Beatgrid overlay dengan glow effects
  - Zoom controls (1x-64x, default 16x)
  - Drag-to-seek navigation
  - Center playhead dengan auto-scrolling
- **Hot Cues**:
  - 8 hot cue pads per deck
  - Instant jump dengan auto-play
  - Visual markers pada waveform
  - Quantize support untuk beat-accurate triggers
- **Audio Playback**: 
  - Web Audio API dengan gain nodes
  - Seekable playback dengan byte-range support
  - Real-time position tracking
  - Independent volume control per deck

## 📁 Struktur Project

```
.
├── src/
│   ├── Parsers/
│   │   ├── PdbParser.php           # Parser export.pdb (DeviceSQL)
│   │   ├── TrackParser.php         # Ekstraksi track metadata
│   │   ├── PlaylistParser.php      # Parser playlist structure
│   │   ├── AnlzParser.php          # Parser ANLZ files (waveform, cue, beatgrid)
│   │   ├── ArtistAlbumParser.php   # Artist & album data
│   │   ├── GenreParser.php         # Genre information
│   │   └── KeyParser.php           # Musical key data
│   ├── Utils/
│   │   └── Logger.php              # Logging system
│   └── RekordboxReader.php         # Main orchestrator class
├── public/
│   ├── js/
│   │   ├── dual-player.js          # DJ player orchestrator
│   │   ├── audio-player.js         # Web Audio API wrapper
│   │   ├── waveform-renderer.js    # Canvas waveform rendering
│   │   ├── cue-manager.js          # Hot cue handler
│   │   └── track-detail.js         # Track detail modal
│   ├── components/
│   │   ├── player.php              # Dual deck player UI
│   │   ├── browser.php             # Library browser
│   │   ├── stats.php               # Statistics
│   │   └── debug.php               # Debug panel
│   ├── partials/
│   │   ├── head.php                # HTML head
│   │   └── footer.php              # JavaScript includes
│   ├── css/
│   │   └── main.css                # All custom styles
│   ├── index.php                   # Main entry point
│   └── audio.php                   # Audio streaming endpoint
├── output/                         # Log files
└── Rekordbox-USB/                  # Rekordbox USB/SD export
    ├── PIONEER/
    │   ├── rekordbox/
    │   │   ├── export.pdb
    │   │   └── exportExt.pdb
    │   └── USBANLZ/                # Analysis files
    │       ├── P000/00000001/
    │       │   ├── ANLZ0000.DAT    # Beatgrid & waveform
    │       │   ├── ANLZ0000.EXT    # Extended waveform (preferred)
    │       │   └── ANLZ0000.2EX    # 3-band waveform (CDJ-3000)
    └── Contents/                   # Audio files (.mp3, .flac, etc)
```

## 🚀 Cara Penggunaan

### Requirements
- PHP 8.2 atau lebih tinggi
- Extension: mbstring (untuk UTF-16LE string handling)
- Browser modern dengan HTML5 Canvas support

### Menjalankan Web GUI

1. Pastikan folder `Rekordbox-USB` berisi export dari Rekordbox
2. Jalankan PHP built-in server:
   ```bash
   cd public
   php -S 0.0.0.0:5000 -t .
   ```
3. Buka browser ke `http://localhost:5000`
4. Data akan otomatis ter-load dan ditampilkan dalam MIXXX-style interface

### Menggunakan DJ Player

#### Loading Tracks
1. Browse tracks dalam library panel
2. Klik **A** atau **B** button untuk load track ke respective deck
3. Tracks auto-load metadata, waveform, dan hot cues

#### Playback Controls
- **Play/Pause**: Klik play button pada masing-masing deck
- **Seek**: Drag waveform atau klik posisi
- **Volume**: Adjust slider (0-100%)

#### Tempo & Beat Matching
1. **Set Master Deck**: Klik **MASTER** button untuk set deck sebagai master (auto-set jika BPM Sync ditekan)
2. **Pitch Slider**: Move slider untuk permanent tempo change (±16%)
   - Saat BPM Sync aktif, pitch master deck otomatis sync ke slave deck
3. **Master Tempo**: Toggle untuk lock musical key
4. **Nudge**: Hold +/- buttons untuk temporary speed adjustment
5. **BPM Sync**: Toggle on/off untuk latching sync mode
   - Saat ON (highlighted), pitch changes master deck otomatis sync ke slave deck
   - Auto-enable master deck jika belum di-set
6. **Beat Sync**: One-time sync tempo + align beat grid phase (momentary button)

#### Hot Cues
1. Klik hot cue pad (1-8) untuk jump ke cue point
2. Enable **Quantize** (Q button) untuk snap to nearest beat
3. Cue markers terlihat pada waveform

### Struktur Rekordbox USB Export

```
Rekordbox-USB/
├── PIONEER/
│   ├── rekordbox/
│   │   ├── export.pdb              # Database utama
│   │   └── exportExt.pdb           # Extended database
│   ├── USBANLZ/                    # Analysis files
│   │   └── P{XXX}/{TrackID}/
│   │       ├── ANLZ0000.DAT        # Beatgrid, basic waveform
│   │       ├── ANLZ0000.EXT        # Extended RGB waveform (most complete)
│   │       └── ANLZ0000.2EX        # 3-band waveform (CDJ-3000 only)
│   └── Artwork/                    # Album artwork
└── Contents/                       # Audio files
```

## 📖 Format Database

### PDB (Pioneer Database)
Database Rekordbox menggunakan format binary proprietary dengan struktur page-based:

- **Header**: Metadata database (page size, table count, sequence number)
- **Tables**: Berbagai tabel untuk tracks, playlists, artists, albums, dll
- **Pages**: Data organized dalam fixed-size pages (4096 bytes) dengan row index
- **Strings**: Custom DeviceSQL encoding (short ASCII, long ASCII, UTF-16LE)
- **Little-endian**: Semua multi-byte values

### ANLZ (Analysis Files)
Files dengan tag-based structure untuk data analysis:

- **PQTZ**: Beatgrid data (beat position, tempo, time) - big-endian format
  - Digunakan untuk BPM Sync dan Beat Sync functionality
  - Menyimpan timing beats, tempo, dan phase information
- **PCOB/PCO2**: Cue points dan loops (big-endian time values)
- **PWAV/PWV3/PWV5**: Waveform data (monochrome & RGB colored)
- **Big-endian**: Berbeda dengan PDB format (critical untuk parsing cue times dan beat grids)

#### ANLZ File Priority
Parser menggunakan prioritas file:
1. `.EXT` files (most complete RGB waveform data)
2. `.DAT` files (basic waveform & beatgrid)
3. `.2EX` files (often empty, CDJ-3000 specific)

## 🎨 UI Design - MIXXX LateNight Theme

Interface dirancang dengan inspirasi dari **MIXXX DJ Software LateNight Skin**:

### Color Palette
- **Background**: `#1a1a1a` (dark base), `#2a2a2a` (elevated surfaces)
- **Primary Accent**: `#00d4ff` (cyan) untuk highlights dan aktif state
- **Text**: `#f5f5f5` (light gray) dengan hierarchy untuk readability
- **Keys**: Color-coded (`#a855f7` minor, `#00d4ff` major)
- **Waveforms**: RGB frequency analysis dengan glow effects

### Layout Components
- **Library Browser**: Track table dengan sortable columns
- **Waveform Display**: Dual canvas (overview + detailed zoom)
- **Hot Cue Pads**: 8-pad grid dengan color gradients
- **Track Info Panel**: BPM, Key, Genre, Duration dengan icons
- **Search Bar**: Real-time filtering dengan FontAwesome search icon

### Icons
Menggunakan **FontAwesome 6.5.1** (CDN):
- `fa-music`: Tracks
- `fa-list`: Playlists
- `fa-fire`: Hot cues
- `fa-check-circle`: Valid tracks
- `fa-triangle-exclamation`: Corrupt data
- Dan lainnya untuk UI consistency

## 🔍 Fitur Teknis

### Canvas Waveform Rendering
- High DPI rendering dengan devicePixelRatio untuk retina/4K displays
- Anti-aliasing dan imageSmoothingQuality = 'high' untuk smooth edges
- Sub-pixel rendering untuk smooth waveform seperti Rekordbox
- Dynamic canvas sizing dengan fallback untuk DOM load timing issues
- RGB color data dari ANLZ files dengan gradient effects
- Softer glow effects dengan optimized shadow rendering
- Click-to-seek pada waveforms

### Cue Point Management
- Parse semua cue types (hot cue, memory cue, loops)
- Render cue markers pada waveform canvas
- Hot cue pads dengan color matching
- Jump-to-cue functionality

### Audio Streaming
- PHP byte-range request handling
- Seekable audio playback
- Synchronization antara playhead dan waveform
- Real-time position tracking

### Error Handling
- Graceful degradation untuk missing ANLZ files
- Corruption detection pada playlist data
- Comprehensive logging untuk debugging
- Fallback rendering untuk missing waveform data

## 📚 Referensi

### File Format Documentation

Project ini didasarkan pada reverse-engineering work dan dokumentasi format Rekordbox:

1. **Deep Symmetry - crate-digger** (Java Implementation)
   - Repository: https://github.com/Deep-Symmetry/crate-digger
   - Kaitai Struct ANLZ: https://github.com/Deep-Symmetry/crate-digger/blob/main/src/main/kaitai/rekordbox_anlz.ksy
   - Kaitai Struct PDB: https://github.com/Deep-Symmetry/crate-digger/blob/main/src/main/kaitai/rekordbox_pdb.ksy
   - Documentation: https://github.com/Deep-Symmetry/crate-digger/tree/main/doc
   - Analysis Guide: https://djl-analysis.deepsymmetry.org/rekordbox-export-analysis/exports.html

2. **Holzhaus - rekordcrate** (Rust Implementation)
   - Repository: https://github.com/holzhaus/rekordcrate
   - PDB Module Docs: https://holzhaus.github.io/rekordcrate/rekordcrate/pdb/index.html
   - ANLZ Module Docs: https://holzhaus.github.io/rekordcrate/rekordcrate/anlz/index.html
   - Settings Module: https://holzhaus.github.io/rekordcrate/rekordcrate/setting/index.html
   - Utils Module: https://holzhaus.github.io/rekordcrate/rekordcrate/util/index.html
   - Main Docs: https://holzhaus.github.io/rekordcrate/rekordcrate/index.html

3. **Henry Betts - Rekordbox Decoding** (C# Implementation)
   - Repository: https://github.com/henrybetts/Rekordbox-Decoding

4. **Digital DJ Tools - DJ Data Converter**
   - Repository: https://github.com/digital-dj-tools/dj-data-converter

5. **MIXXX DJ Software** (Open Source DJ Application)
   - Repository: https://github.com/mixxxdj/mixxx
   - Website: https://mixxx.org
   - UI Design Inspiration: MIXXX LateNight Skin

### Technical Resources

- **Rekordbox Database Format**: DeviceSQL (SQLite-like proprietary format)
- **ANLZ Files**: Tag-based binary format untuk waveform, beatgrid, dan cue points
- **Endianness**: 
  - PDB uses little-endian
  - ANLZ uses big-endian (critical untuk cue point timestamps)
- **String Encoding**: Short ASCII, Long ASCII, UTF-16LE (in PDB)

## 🐛 Known Issues & Solutions

### Waveform Canvas Width = 0
**Solution**: Canvas setup dipanggil ulang saat `loadWaveform()` jika width masih 0, dengan fallback ke parent width atau default 800px.

### ANLZ Cue Point Times Incorrect
**Solution**: Menggunakan big-endian (`N` format) untuk time fields, bukan little-endian (`V`).

### Empty .2EX Files
**Solution**: File priority order `.EXT` → `.DAT` → `.2EX`, dengan `hasData` flag untuk skip empty files.

## 📝 License

Project ini dibuat untuk tujuan educational dan interoperability.

## 🙏 Credits

Reverse-engineering format Rekordbox dilakukan oleh:
- **Henry Betts** (@henrybetts) - Rekordbox Decoding (C#)
- **Fabian Lesniak** (@flesniak) - Python ProDJ Link
- **James Elliott** (@brunchboy) - Deep Symmetry / Crate Digger (Java)
- **Jan Holthuis** (@Holzhaus) - Rekordcrate (Rust)
- **MIXXX Development Team** - UI/UX Design Inspiration

Special thanks kepada seluruh komunitas yang berkontribusi dalam reverse-engineering format Rekordbox untuk mendukung interoperability di ekosistem DJ software.

---

**v2.1 - Enhanced BPM Sync & Smooth Waveforms** | Powered by PHP 8.2 | UI inspired by MIXXX DJ Software

### 📋 Recent Updates (v2.1)

#### BPM Sync Enhancements
- ✅ **Latching BPM Sync**: Toggle on/off mode seperti Quantize button
- ✅ **Auto-Sync Pitch Slider**: Saat BPM Sync ON, pitch master deck otomatis sync ke slave deck
- ✅ **Auto Master Enable**: Master deck otomatis di-set saat sync ditekan jika belum ada master
- ✅ **Active State Indication**: Visual feedback dengan button highlight

#### Waveform Rendering Improvements
- ✅ **High DPI Support**: devicePixelRatio untuk retina/4K displays
- ✅ **Anti-Aliasing**: imageSmoothingQuality = 'high' untuk smooth edges
- ✅ **Sub-Pixel Rendering**: Smooth waveform rendering seperti Rekordbox
- ✅ **Optimized Glow Effects**: Softer shadows untuk better visual quality

#### Beat Grid Parsing
- ✅ **PQTZ Section Parsing**: Extract beat grid data dari ANLZ files (big-endian)
- ✅ **Accurate Tempo Data**: Beat positions, tempo, dan timing information

#### User Experience
- ✅ **In-UI Notifications**: Toast notifications menggantikan JavaScript alerts
- ✅ **Better Error Messages**: Contextual feedback untuk user actions

## 🎛️ DJ Player Features Summary

| Feature | Description |
|---------|-------------|
| **Dual Decks** | Independent playback pada Deck A & B |
| **Pitch Control** | ±16% tempo adjustment dengan real-time BPM |
| **Master Tempo** | Key lock saat adjust tempo |
| **Tempo Nudge** | Temporary ±4% speed adjustment |
| **Volume** | Independent 0-100% control per deck |
| **BPM Sync** | Latching toggle - auto-sync pitch slider saat ON |
| **Beat Sync** | Momentary - center-point aligned beat grid sync |
| **Quantize** | Snap to beat untuk hot cues |
| **Hot Cues** | 8 pads per deck dengan instant jump |
| **Waveform** | Color RGB dengan beatgrid overlay |
| **Zoom** | 1x-64x (default 16x) dengan drag |
| **Center Playhead** | Auto-scrolling waveform |
