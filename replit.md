# Rekordbox Export Reader

## Overview
A comprehensive Python project untuk membaca dan memproses database ekspor Pioneer Rekordbox dari USB/SD card. Project ini menyediakan framework lengkap dengan struktur modular untuk parsing file PDB (export.pdb) dan ANLZ (analysis files).

## Project Structure

### Core Modules
- `rekordbox_reader/parsers/` - Parser modules
  - `pdb_parser.py` - PDB database parser (header, tables, pages)
  - `track_parser.py` - Track data extractor
  - `playlist_parser.py` - Playlist parser dengan corruption detection
  - `anlz_parser.py` - ANLZ analysis file parser
  
- `rekordbox_reader/utils/` - Utility modules
  - `logger.py` - Logging system dengan corrupt playlist tracking

- `rekordbox_reader/tests/` - Unit tests (11 tests passing)
  - `test_pdb_parser.py` - PDB parser tests
  - `test_playlist_parser.py` - Playlist parser dan corruption detection tests

- `rekordbox_reader/examples/` - Example scripts
  - `create_mock_export.py` - Generate mock Rekordbox export untuk testing

### Main Files
- `rekordbox_reader/main.py` - CLI interface lengkap
- `run.py` - Quick run script
- `demo.py` - Demo script yang menjalankan tests
- `README.md` - Dokumentasi lengkap (bahasa Indonesia & English)

## Technology Stack
- Python 3.11
- pytest untuk testing
- colorama untuk colored logging
- Binary parsing dengan struct module

## Implementation Status

### ✅ Completed (Framework & Architecture)
1. **Project Structure** - Modular architecture dengan separation of concerns
2. **PDB Parser** - Header parsing, table pointer extraction, page reading
3. **Logging System** - Comprehensive logging dengan corrupt playlist tracking
4. **Error Handling** - Framework untuk corruption detection dan safe skipping
5. **CLI Interface** - Complete command-line interface dengan argparse
6. **Unit Tests** - 11 unit tests untuk core functionality
7. **Mock Data** - Generator untuk create test exports
8. **Documentation** - Comprehensive README dengan usage examples

### 🚧 Requires Full Implementation (Binary Parsing)
1. **Row-Level Parsing** - Complete implementation untuk extract actual rows dari PDB pages
2. **Track Extraction** - Parse track metadata (title, artist, BPM, etc.) dari binary heap
3. **Playlist Parsing** - Full playlist tree dan entry extraction
4. **Corruption Detection** - Detailed checks untuk offset validation, structure integrity
5. **ANLZ Parsing** - Complete beatgrid, waveform, dan cue point extraction

## How It Works

### Current Implementation
The project provides:
- Framework untuk parsing Rekordbox binary database format
- Header dan table structure extraction
- Page-level reading dari PDB files
- Skeleton untuk track, playlist, dan ANLZ parsing
- Error handling dan logging infrastructure

### What's Needed
Untuk complete functionality, perlu implement:
1. Binary row data extraction menggunakan heap + row index structure
2. DeviceSQL string decoding (ASCII, UTF-16LE variants)
3. Type-specific row parsing untuk each table type
4. Cross-reference resolution (tracks ↔ playlists)

## Usage

### Run Demo & Tests
```bash
# Run demo dengan tests
python demo.py

# All 11 unit tests should pass
```

### Run with Real Export
```bash
# Basic usage
python run.py /path/to/usb/export

# Dengan verbose logging
python run.py /path/to/usb/export -v

# Custom output directory
python run.py /path/to/usb/export -o my_output
```

### Run Tests Only
```bash
pytest rekordbox_reader/tests/ -v
```

## Features Implemented

### ✅ Logging & Error Handling
- Colored console output
- File logging dengan timestamps
- Corrupt playlist tracking ke JSON
- Statistics collection

### ✅ CLI Interface
- Argparse-based CLI
- Verbose mode
- Custom output directory
- Help documentation

### ✅ Testing Infrastructure
- Unit tests untuk parsers
- Mock data generation
- Test coverage untuk corruption handling

## File Format References

Project ini based on reverse-engineering research:
- [rekordcrate](https://github.com/holzhaus/rekordcrate) - Rust implementation
- [crate-digger](https://github.com/Deep-Symmetry/crate-digger) - Java dengan Kaitai Struct specs
- [DJ Link Analysis](https://djl-analysis.deepsymmetry.org/) - Comprehensive documentation

## Development Notes

### Binary Format Complexity
Rekordbox PDB format adalah complex binary database dengan:
- Page-based architecture (variable page sizes)
- Heap storage untuk variable-length data
- Row index built backwards dari page end
- Bitmap untuk indicate present rows
- Custom string encoding (3 variants)
- Little-endian integers

### Next Steps untuk Full Implementation
1. Implement `_parse_track_page()` dengan actual row extraction
2. Implement `_parse_playlist_page()` dengan real corruption checks
3. Add DeviceSQL string decoder lengkap
4. Implement ANLZ section parsers untuk beatgrid/waveform
5. Add integration tests dengan real Rekordbox exports

## License
MIT License

## Recent Changes (Nov 20, 2025)
- Created complete project structure
- Implemented framework untuk PDB, Track, Playlist, dan ANLZ parsers
- Added comprehensive logging system dengan corruption tracking
- Created 11 unit tests (all passing)
- Added mock data generator
- Created detailed README documentation
- Configured demo workflow
