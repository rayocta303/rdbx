# Rekordbox Export Reader - PHP Edition

## Overview
Tool PHP untuk membaca dan menampilkan database Rekordbox USB/SD export dengan web GUI modern. Project ini mengkonversi implementasi Python menjadi PHP murni dengan struktur modular.

## Recent Changes
- **2025-11-20**: Konversi lengkap dari Python ke PHP
  - Implementasi PdbParser untuk membaca format DeviceSQL
  - TrackParser untuk ekstraksi metadata track
  - PlaylistParser dengan corruption detection
  - AnlzParser untuk beatgrid dan waveform analysis
  - Web GUI dengan Tailwind CDN (no build tools)
  - Auto-load data tanpa API endpoint

## Project Architecture

### Core Parsers (src/Parsers/)
- **PdbParser.php**: Membaca export.pdb (DeviceSQL format, little-endian)
- **TrackParser.php**: Parse track rows (title, artist, BPM, key, dll)
- **PlaylistParser.php**: Parse playlist tree dengan corruption handling
- **AnlzParser.php**: Parse ANLZ files (beatgrid, waveform, cue points, big-endian)

### Main Components
- **RekordboxReader.php**: Orchestrator yang mengkoordinasikan semua parser
- **Logger.php**: Logging system dengan corrupt playlist tracking
- **public/index.php**: Web GUI dengan PHP server-side rendering

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
- TrackParser perlu perbaikan struktur byte untuk match spesifikasi Deep Symmetry
- ANLZ beatgrid dan cue point extraction masih stub implementation

## References
- Deep Symmetry crate-digger: https://github.com/Deep-Symmetry/crate-digger
- Kaitai Struct spec: rekordbox_pdb.ksy
- Documentation: https://djl-analysis.deepsymmetry.org/
