# Rekordbox Export Reader - PHP Edition

Tool PHP untuk membaca, mem-parse, dan menampilkan data dari database ekspor Rekordbox (PDB files) dengan Web GUI.

## ✨ Fitur

### 📀 Parsing Database Lengkap
- Membaca file `export.pdb` dan `exportExt.pdb`
- Ekstraksi track metadata (title, artist, album, BPM, key, duration)
- Parsing playlist structure dan folder hierarchy
- Ekstraksi cue points dan beatgrid information dari ANLZ files
- Corruption detection untuk playlist yang rusak

### 🎨 Web GUI Modern
- Interface berbasis Tailwind CSS (CDN - no build tools)
- Real-time search dan filter tracks
- Tab navigation (Tracks, Playlists, Metadata)
- Statistics dashboard
- Responsive design

### 🔧 Arsitektur Modular
- Struktur kode PHP yang terorganisir dan mudah di-maintenance
- Pemisahan concerns (Parsers, Utils, Views)
- No framework dependencies - PHP murni
- Logging system dengan corruption tracking

## 📁 Struktur Project

```
.
├── src/
│   ├── Parsers/
│   │   ├── PdbParser.php       # Parser untuk export.pdb (DeviceSQL format)
│   │   ├── TrackParser.php     # Ekstraksi metadata track
│   │   ├── PlaylistParser.php  # Parser playlist dengan corruption handling
│   │   └── AnlzParser.php      # Parser ANLZ files (beatgrid, waveform, cue)
│   ├── Utils/
│   │   └── Logger.php          # Logging system
│   └── RekordboxReader.php     # Main class orchestrator
├── public/
│   └── index.php               # Web GUI dengan Tailwind CDN
├── output/                     # Log files dan hasil parsing
└── Rekordbox-USB/              # Directory USB/SD Rekordbox export
    └── PIONEER/
        └── rekordbox/
            ├── export.pdb
            └── exportExt.pdb
```

## 🚀 Cara Penggunaan

### Requirements
- PHP 8.2 atau lebih tinggi
- Extension: mbstring (untuk UTF-16LE string handling)

### Menjalankan Web GUI

1. Pastikan folder `Rekordbox-USB` berisi export dari Rekordbox
2. Jalankan PHP built-in server:
   ```bash
   php -S 0.0.0.0:5000 -t public
   ```
3. Buka browser ke `http://localhost:5000`
4. Data akan otomatis ter-load dan ditampilkan

### Struktur Rekordbox USB Export

```
Rekordbox-USB/
├── PIONEER/
│   ├── rekordbox/
│   │   ├── export.pdb       # Database utama
│   │   └── exportExt.pdb    # Extended database
│   ├── USBANLZ/              # Analysis files
│   │   ├── P000/
│   │   │   └── 00000001/
│   │   │       ├── ANLZ0000.DAT  # Beatgrid, waveform
│   │   │       ├── ANLZ0000.EXT  # Extended waveform
│   │   │       └── ANLZ0000.2EX  # 3-band waveform (CDJ-3000)
│   └── Artwork/              # Album artwork
└── Contents/                 # Audio files
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

- **PQTZ**: Beatgrid data (beat position, tempo, time)
- **PCOB/PCO2**: Cue points dan loops
- **PWAV/PWV3/PWV5**: Waveform data (monochrome & colored)
- **Big-endian**: Berbeda dengan PDB format

## 🔍 Fitur Corruption Handling

Parser secara otomatis:
- Mendeteksi playlist yang corrupt/rusak
- Melewati data yang invalid tanpa crash
- Logging semua corruption yang ditemukan
- Melanjutkan parsing untuk data yang valid

## 📚 Referensi

Project ini didasarkan pada reverse-engineering work dari:

1. **Deep Symmetry - crate-digger** (Java)
   - https://github.com/Deep-Symmetry/crate-digger
   - Dokumentasi: https://djl-analysis.deepsymmetry.org/

2. **Holzhaus - rekordcrate** (Rust)
   - https://github.com/holzhaus/rekordcrate

3. **Henry Betts - Rekordbox Decoding**
   - https://github.com/henrybetts/Rekordbox-Decoding

4. **Fabian Lesniak - python-prodj-link** (Python)
   - https://github.com/mtgto/python-prodj-link

## 📝 License

Project ini dibuat untuk tujuan educational dan interoperability.

## 🙏 Credits

Reverse-engineering format Rekordbox dilakukan oleh:
- Henry Betts (@henrybetts)
- Fabian Lesniak (@flesniak)
- James Elliott (@brunchboy) - Deep Symmetry
- Jan Holthuis (@Holzhaus)
