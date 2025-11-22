# Rekordbox Export Reader - PHP Edition

## Overview
This project is a PHP-based web GUI tool designed to read and display Rekordbox USB/SD export databases. It's a re-implementation of a Python tool into a pure PHP, modular structure. The primary goal is to provide a modern web interface for DJ-specific functionalities, including dual-deck playback, waveform visualization, beat grid analysis, hot cue management, and advanced synchronization features, mirroring the professional experience of Rekordbox.

## Recent Changes (November 2025)
- **CSS and UI Refactoring (November 22)**: Complete refactoring untuk styling yang lebih clean dan professional:
  - Renamed all CSS classes dari `mixxx-*` ke `app-*` (app-container, app-header)
  - Merged stats.php dan debug.php menjadi satu debug.php dengan tabbed interface (Statistics, Database Metadata)
  - Applied industrial, clean, minimalist design dengan no glow effects
  - Updated .app-container dengan proper padding (1.25rem), gradient background, subtle box-shadow
  - Consistent Tailwind color usage (gray-800, slate-700, cyan-400) untuk professional look
  - Updated README.md untuk remove "MIXXX Edition" references dan reduce emoji
  - Created comprehensive DOCUMENTATION.md untuk semua parsers dan utilities
  - Removed /stats route dari router.php
- **Comprehensive Table View Page (November 22)**: Membuat halaman /table untuk menampilkan semua data export.pdb:
  - Database Overview tab: PDB header dan metadata semua tabel
  - Tracks tab: Menampilkan SEMUA 24 field termasuk raw IDs (artist_id, album_id, genre_id, key_id, color_id, artwork_id, track_number, bitrate, sample_rate, play_count, file_size, comment, analyze_path, dll)
  - Complete coverage: Playlists, Playlist Entries, Artists, Albums, Genres, Keys
  - Partial coverage: Colors, Labels, Artwork (parser sederhana, perlu improvement untuk binary structure parsing yang kompleks)
  - History dan Columns: Ditandai sebagai "Not Implemented" (memerlukan advanced parser development)
- **Page Structure Refactoring (November 22)**: Reorganisasi struktur halaman untuk modularitas yang lebih baik:
  - Created `pages/` directory untuk halaman standalone (debug)
  - Implemented clean URL router tanpa ekstensi `.php` (menggunakan `router.php`)
  - Setiap halaman sekarang self-contained tanpa dependency ke dual-player.js
  - Router handles trailing slashes untuk URL yang lebih fleksibel
  - Added navigation buttons di homepage untuk akses ke debug dan table pages
- **Waveform Rendering Rebuild (November 21)**: Complete rebuild dari scratch untuk maksimum efisiensi:
  - Simplified architecture tanpa caching overhead - rendering on-demand saja
  - Single-path rendering per band untuk minimal canvas operations
  - RAF-batched resize handler untuk menghindari rendering storm di multiple instances
  - DPR handling dengan setTransform() untuk proper scaling
  - Float32Array downsampling untuk memory efficiency
  - Max-aggregation untuk mencegah aliasing
  - Clean code structure yang mudah di-maintain
  - Implementasi mengikuti prinsip efisiensi dari reference Waveform.html
- **Beat Grid Rendering Optimization**: Changed from per-beat stroke operations to single-path rendering. This reduces canvas operations from N strokes to 1 stroke per frame, significantly improving performance on low-end devices.
- **Amplitude Normalization Fix**: Corrected amplitude scaling - backend data is already normalized to 0-1 range, removed incorrect /255 division that was collapsing waveform visibility.

## User Preferences
- Menggunakan PHP murni tanpa framework
- Struktur modular dan terorganisir
- Tailwind CDN untuk CSS (no build tools)
- Bahasa Indonesia untuk dokumentasi

## System Architecture

### UI/UX Decisions
- Modern web GUI with a clean, professional aesthetic inspired by DJ software like Rekordbox, Mixxx, and Serato.
- Utilizes Tailwind CSS via CDN for rapid styling without build tools.
- Dual-deck DJ player interface with dedicated controls for each deck.
- Canvas-based waveform visualization featuring 3-band waveform rendering (Orange for low/bass, White for mid, Blue for high/treble), rounded bars, and anti-aliasing.
- Interactive waveform scrubbing with high-DPI display support.
- Integrated hot cue pads (8 per deck) with visual feedback.
- In-UI notifications replacing standard JavaScript alerts for a better user experience.
- Gold styling for active Master deck button.

### Technical Implementations
- **Core Parsers (PHP)**:
    - `PdbParser.php`: Reads `export.pdb` files (DeviceSQL format, little-endian).
    - `TrackParser.php`: Extracts track metadata (title, artist, BPM, key, etc.).
    - `PlaylistParser.php`: Parses playlist trees with corruption handling.
    - `AnlzParser.php`: Parses ANLZ files (`.DAT`, `.EXT`, `.2EX`) for beatgrids (PQTZ), waveforms (PWAV/PWV5), and cue points (PCO2/PCOB), handling big-endian byte order. Merges data preferring detailed waveform data from `.EXT` files.
- **Frontend (JavaScript)**:
    - `dual-player.js`: Orchestrates the dual-deck player functionality. Includes optimized path-based beat grid rendering for low-end devices.
    - `audio-player.js`: Web Audio API wrapper for playback, including async user interaction handling for browser autoplay policies.
    - `waveform-renderer.js`: Handles canvas-based waveform drawing dengan simplified, highly efficient rendering:
      - On-demand rendering tanpa caching overhead untuk low-end devices
      - Single-path rendering per band (low/mid/high) untuk minimal canvas operations
      - RAF-batched resize handler untuk multiple instances tanpa rendering storm
      - Float32Array downsampling dengan max-aggregation untuk smooth results
      - 3-band frequency visualization (low=white, mid=orange, high=blue)
      - DPR-aware scaling dengan setTransform()
      - Clean, maintainable code structure
    - `cue-manager.js`: Manages hot cue triggering and markers, supporting Rekordbox-style 0-indexed pads.
    - `track-detail.js`: Displays detailed track information in a modal.

### Feature Specifications
- **Dual DJ Player**: Independent controls for two decks including play/pause, hot cues, volume, pitch control, and synchronization features.
- **Waveform Visualization**: Displays detailed waveforms with beat grid overlay, zoom functionality (16x-128x), and drag-to-scratch interaction, including reverse audio scratching.
- **Hot Cue Management**: 8 hot cue pads per deck, supporting trigger functionality and displaying assigned labels.
- **BPM Pitch Control**: Adjustable pitch slider (-16% to +16%) with Master Tempo toggle.
- **Tempo Nudge**: Temporary speed adjustments (±4%) for beat matching.
- **Volume Control**: Independent volume sliders per deck.
- **Synchronization**:
    - **BPM Sync**: Synchronizes BPMs between decks.
    - **Beat Sync**: Aligns beat grids and phases for perfect beat matching using Rekordbox beat grid offsets.
    - **Quantize**: Snaps hot cue triggers and playback to the nearest beat using Rekordbox beat grid data.
- **Master Deck Selection**: Rekordbox-style MASTER button on each deck for push/pull sync logic.
- **Modular Frontend**: 
    - `index.php`: Main entry point dengan dual-deck player dan library browser
    - `pages/stats.php`: Standalone statistics page (`/stats`)
    - `pages/debug.php`: Standalone debug panel (`/debug`)
    - `router.php`: Clean URL router untuk navigasi tanpa ekstensi file

### System Design Choices
- **Pure PHP 8.2**: No external PHP frameworks are used for core logic.
- **Modular Structure**: Codebase is organized into logical components for parsers, orchestrators, and frontend parts for maintainability.
- **DeviceSQL and ANLZ Parsing**: Custom parsers handle complex Rekordbox database formats, including byte order differences between PDB and ANLZ files.
- **Client-Side Rendering**: Waveforms and DJ controls are rendered and managed client-side using JavaScript and the Web Audio API for responsiveness.

## External Dependencies
- **Tailwind CSS CDN**: Used for all styling.
- **Web Audio API**: For audio playback and manipulation in the browser.
- **Rekordbox Database Files**:
    - `export.pdb`: DeviceSQL database containing track and playlist metadata.
    - `.ANLZ` files (`.DAT`, `.EXT`, `.2EX`): Contain beat grid, waveform, and cue point data.