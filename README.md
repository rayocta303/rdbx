# Rekordbox Export Reader

**Parser komprehensif untuk membaca dan memproses database ekspor Pioneer Rekordbox dari USB/SD card.**

[![Python 3.11+](https://img.shields.io/badge/python-3.11+-blue.svg)](https://www.python.org/downloads/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## 📋 Deskripsi

Rekordbox Export Reader adalah tool Python yang dapat membaca, mem-parse, dan mengekstraksi data dari database ekspor Rekordbox (PDB files). Tool ini dirancang khusus untuk menangani playlist yang corrupt dengan aman dan melanjutkan proses parsing tanpa error.

### Fitur Utama

✅ **Parsing Database Lengkap**
- Membaca file `export.pdb` dan `exportExt.pdb`
- Ekstraksi track metadata (title, artist, album, BPM, key, duration)
- Parsing playlist structure dan folder hierarchy
- Ekstraksi cue points dan beatgrid information

✅ **Deteksi Corrupt Playlists**
- Deteksi otomatis playlist yang corrupt
- Skip playlist corrupt tanpa menghentikan proses
- Log lengkap playlist corrupt ke file JSON
- Melanjutkan parsing dengan aman

✅ **Analisis File ANLZ**
- Parse file beatgrid (.DAT)
- Ekstraksi waveform data (.EXT, .2EX)
- Cue points dan loop markers
- Song structure analysis

✅ **Output Fleksibel**
- Export ke JSON format
- Summary statistik lengkap
- Logging berwarna di console
- File log terpisah untuk debugging

## 🔧 Struktur Database Rekordbox

### Format PDB (Pioneer Database)

Database Rekordbox menggunakan format binary proprietary dengan struktur page-based:

- **Header**: Metadata database (page size, table count, sequence number)
- **Tables**: Berbagai tabel untuk tracks, playlists, artists, albums, dll
- **Pages**: Data organized dalam fixed-size pages dengan row index
- **Strings**: Custom encoding (short ASCII, long ASCII, UTF-16LE)

### Format ANLZ (Analysis Files)

File analisis berisi:
- **Beatgrid**: Beat timestamps dan tempo information
- **Waveform**: Preview dan detail waveforms (mono/color)
- **Cue Points**: Memory cues, hot cues, dan loops
- **Song Structure**: Phrase analysis (intro, verse, chorus, etc)

## 🚀 Instalasi

### Requirements

- Python 3.11 atau lebih baru
- Dependencies: pytest, colorama

### Setup

```bash
# Install dependencies
pip install -r requirements.txt

# Atau manual install
pip install pytest pytest-cov colorama
```

## 💻 Cara Menggunakan

### Basic Usage

```bash
# Parse Rekordbox export dari USB/SD
python run.py /path/to/usb/export

# Dengan verbose logging
python run.py /path/to/usb/export -v

# Custom output directory
python run.py /path/to/usb/export -o my_output
```

### Command Line Options

```
positional arguments:
  export_path           Path ke Rekordbox USB/SD export (containing PIONEER directory)

optional arguments:
  -h, --help            Show help message
  -o, --output OUTPUT   Output directory untuk JSON dan logs (default: output)
  -v, --verbose         Enable verbose debug logging
```

### Contoh Output

```
=============================================================
Rekordbox Export Reader - Starting...
==============================================================
INFO - Reading database: /path/to/PIONEER/rekordbox/export.pdb
INFO - Parsing tracks dari database...
INFO - Total 150 tracks berhasil di-parse
INFO - Parsing playlists dari database...
WARNING - Playlist corrupt dilewati: "Old Mix 2023" - Invalid playlist structure detected
INFO - Playlist parsing selesai: 12 valid, 1 corrupt (dilewati)
INFO - Found 300 ANLZ files
INFO - Output saved to: output/rekordbox_export_20251120_120000.json

==============================================================
PROCESSING SUMMARY
==============================================================
Total Tracks:           150
Total Playlists:        13
  - Valid:              12
  - Corrupt (skipped):  1
ANLZ Files Processed:   5
Processing Time:        2.45 seconds
==============================================================
```

## 📁 Struktur Project

```
rekordbox_reader/
├── parsers/              # Parser modules
│   ├── pdb_parser.py     # PDB database parser
│   ├── track_parser.py   # Track data extractor
│   ├── playlist_parser.py # Playlist parser dengan corruption handling
│   └── anlz_parser.py    # ANLZ analysis file parser
├── utils/                # Utility modules
│   └── logger.py         # Logging system dengan corrupt playlist tracking
├── tests/                # Unit tests
│   ├── test_pdb_parser.py
│   └── test_playlist_parser.py
├── examples/             # Example scripts
│   └── create_mock_export.py  # Generate mock data untuk testing
└── main.py               # Main CLI interface
```

## 🧪 Testing

### Run Unit Tests

```bash
# Run semua tests
pytest

# Dengan coverage report
pytest --cov=rekordbox_reader

# Verbose output
pytest -v

# Run specific test file
pytest rekordbox_reader/tests/test_playlist_parser.py
```

### Create Mock Data

```bash
# Generate mock Rekordbox export untuk testing
python rekordbox_reader/examples/create_mock_export.py
```

## 📊 Output Files

Tool ini menghasilkan beberapa file output:

### 1. Main JSON Output
`rekordbox_export_YYYYMMDD_HHMMSS.json`
```json
{
  "tracks": [...],
  "playlists": [...],
  "metadata": {
    "export_path": "/path/to/export",
    "parsed_at": "2025-11-20T12:00:00",
    "pdb_header": {...}
  }
}
```

### 2. Corrupt Playlists Log
`corrupt_playlists.json`
```json
[
  {
    "playlist_name": "Old Mix 2023",
    "reason": "Invalid playlist structure detected",
    "details": {"page_size": 30},
    "timestamp": "2025-11-20T12:00:00"
  }
]
```

### 3. Statistics
`stats_YYYYMMDD_HHMMSS.json`
```json
{
  "total_tracks": 150,
  "valid_playlists": 12,
  "corrupt_playlists": 1,
  "processing_time": 2.45
}
```

### 4. Log File
`rekordbox_reader_YYYYMMDD_HHMMSS.log`

## 🔍 Deteksi Playlist Corrupt

Tool ini mendeteksi corrupt playlists berdasarkan:

1. **Page Size Invalid**: Page terlalu kecil (<48 bytes)
2. **Offset Salah**: Row offset di luar bounds
3. **Struktur Tidak Lengkap**: Missing required fields
4. **Format Error**: Tidak bisa decode data
5. **Data Invalid**: Null bytes atau invalid sequences

Ketika playlist corrupt terdeteksi:
- Playlist di-skip tanpa crash
- Logging detail ke console dan file
- Entry disimpan ke `corrupt_playlists.json`
- Parsing lanjut ke playlist berikutnya

## 🛠️ Development & Extensibility

### Menambahkan Fitur Baru

#### 1. Parser Baru untuk Table Lain

```python
from rekordbox_reader.parsers.pdb_parser import PDBParser

class GenreParser:
    def __init__(self, pdb_parser, logger=None):
        self.pdb_parser = pdb_parser
        self.logger = logger
    
    def parse_genres(self):
        genre_table = self.pdb_parser.get_table(PDBParser.TABLE_GENRES)
        # Implement genre parsing logic
        return genres
```

#### 2. Custom Output Format

```python
# Modify _save_output in main.py
def _save_output_csv(self, result):
    import csv
    with open('tracks.csv', 'w') as f:
        writer = csv.DictWriter(f, fieldnames=['title', 'artist', 'bpm'])
        writer.writeheader()
        writer.writerows(result['tracks'])
```

#### 3. Additional Corruption Checks

```python
# Add to playlist_parser.py
def _detect_corruption(self, page_data, playlist_name):
    # Existing checks...
    
    # New check: Verify playlist name encoding
    if not self._is_valid_utf8(playlist_name):
        return True
    
    return False
```

## 📚 Referensi

Project ini menggunakan reverse-engineering research dari:

- [rekordcrate](https://github.com/holzhaus/rekordcrate) - Rust library untuk Rekordbox parsing
- [crate-digger](https://github.com/Deep-Symmetry/crate-digger) - Java library dengan Kaitai Struct specs
- [djl-analysis](https://github.com/Deep-Symmetry/djl-analysis) - Comprehensive DJ Link ecosystem analysis
- [python-prodj-link](https://github.com/mtgto/python-prodj-link) - Python implementation
- [rb-pdb](https://github.com/monkeyswarm/rb-pdb) - PDB format documentation

### Documentation Resources

- [DJ Link Ecosystem Analysis](https://djl-analysis.deepsymmetry.org/rekordbox-export-analysis/)
- [Kaitai Struct Specs](https://github.com/Deep-Symmetry/crate-digger/blob/main/src/main/kaitai/rekordbox_pdb.ksy)

## 🤝 Contributing

Contributions welcome! Areas untuk improvement:

- [ ] Implement complete row parsing dari page heap
- [ ] Add support untuk exportExt.pdb extended metadata
- [ ] Improve ANLZ waveform extraction
- [ ] Add SQLite export option
- [ ] Create GUI interface
- [ ] Performance optimization untuk large libraries

## 📄 License

MIT License - See LICENSE file for details

## ⚠️ Disclaimer

Tool ini dibuat untuk educational dan research purposes. Rekordbox dan Pioneer DJ adalah trademark dari AlphaTheta Corporation. Tool ini tidak affiliated dengan AlphaTheta Corporation.

## 👥 Authors

Rekordbox Export Reader Team

---

**Built with** ❤️ **for the DJ community**
