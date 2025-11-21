# Rekordbox Export Reader - PHP Edition

## Overview
This project is a PHP-based web GUI tool designed to read and display Rekordbox USB/SD export databases. It's a re-implementation of a Python tool into a pure PHP, modular structure. The primary goal is to provide a modern web interface for DJ-specific functionalities, including dual-deck playback, waveform visualization, beat grid analysis, hot cue management, and advanced synchronization features, mirroring the professional experience of Rekordbox.

## Recent Changes (November 2025)
- **Waveform Rendering Optimization**: Refactored to path-based rendering approach, eliminating per-bar drawing overhead. Implemented Float32Array for memory efficiency and max-aggregation downsampling to prevent aliasing.
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
    - `waveform-renderer.js`: Handles canvas-based waveform drawing with path-based rendering. Features Float32Array for memory efficiency, max-aggregation downsampling, and 3-band frequency visualization (low/mid/high). Optimized for low-end devices with single-path rendering instead of per-bar drawing.
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
- **Modular Frontend**: `index.php` acts as the entry point, orchestrating smaller PHP components for statistics, browser, player, and debug panels.

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