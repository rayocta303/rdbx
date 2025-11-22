# Rekordbox Export Reader

Professional DJ Library Manager dengan Dual Deck Player untuk membaca dan memainkan Rekordbox USB exports.

## Fitur Utama

### Professional Dual DJ Player
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
  - Uses actual Rekordbox beat grid offsets (PQTZ section dari ANLZ files)
  - Momentary button untuk one-time sync dengan visual feedback
- **Quantize**: Snap to nearest beat functionality
  - Toggle per deck
  - Applies to hot cue triggers
  - Uses Rekordbox beat grid untuk accuracy

### Professional DJ Interface
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

### Database Parsing
- Membaca file `export.pdb` dan `exportExt.pdb` (DeviceSQL format)
- Ekstraksi track metadata (title, artist, album, BPM, key, duration)
- Parsing playlist structure dan folder hierarchy
- **ANLZ File Integration**: Parse waveform dan cue points dari `USBANLZ/*.DAT/*.EXT/*.2EX`
- Audio streaming dengan byte-range support untuk playback
- Corruption detection dan graceful error handling
- **Linked-List Page Traversal**: Mengikuti struktur next_page untuk parsing data yang lengkap

---

## Struktur Data Rekordbox Database

### DeviceSQL Format (PDB Files)

Rekordbox menggunakan format database binary proprietary yang disebut **DeviceSQL**, mirip dengan SQLite namun dengan struktur custom yang dioptimasi untuk embedded devices (16-bit devices dengan 32KB RAM).

#### File Structure Overview

Database terdiri dari **fixed-size blocks/pages** (default: 4096 bytes):

```
┌─────────────────────────────────────────────────────┐
│ Block 0: Database Header                            │
│  - Signature (4 bytes)                              │
│  - Page size (4 bytes) - typically 0x1000 (4096)    │
│  - Number of tables (4 bytes)                       │
│  - Next unused page (4 bytes)                       │
│  - Sequence number (4 bytes)                        │
│  - Table directory (16 bytes × N tables)            │
└─────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────┐
│ Blocks 1..N: Data Pages                             │
│  - Page header (40 bytes)                           │
│  - Row data heap (variable size)                    │
│  - Row index (builds backwards from end)            │
└─────────────────────────────────────────────────────┘
```

#### Kaitai Struct Specification

Format PDB mengikuti spesifikasi [rekordbox_pdb.ksy](https://github.com/mixxxdj/mixxx/blob/main/lib/rekordbox-metadata/rekordbox_pdb.ksy):

**Database Header** (offset 0x00):
```yaml
seq:
  - type: u4           # Signature (always 0)
  - id: len_page       # Page size (4096 bytes)
    type: u4
  - id: num_tables     # Number of tables
    type: u4
  - id: next_unused_page
    type: u4
  - type: u4           # Unknown
  - id: sequence       # Sequence counter
    type: u4
  - id: tables         # Table entries
    type: table
    repeat: expr
    repeat-expr: num_tables
```

**Table Entry** (16 bytes each):
```yaml
table:
  seq:
    - id: type         # Table type (0=tracks, 1=genres, etc.)
      type: u4
      enum: page_type
    - id: empty_candidate
      type: u4
    - id: first_page   # Index of first page
      type: page_ref
    - id: last_page    # Index of last page
      type: page_ref
```

**Page Header** (40 bytes):
```yaml
page:
  seq:
    - type: u4           # Gap/padding
    - id: page_index     # Page number
      type: u4
    - id: type           # Page type
      type: u4
      enum: page_type
    - id: next_page      # Linked list to next page
      type: page_ref
    - type: u4           # Sequence
    - type: u4           # Unknown
    - id: num_rows_small # Row count (small)
      type: u1
    - type: u1           # Unknown
    - type: u1           # Unknown
    - id: page_flags     # Flags (0x40 = index page)
      type: u1
    - id: free_size      # Free heap space
      type: u2
    - id: used_size      # Used heap space
      type: u2
    - type: u2           # Unknown
    - id: num_rows_large # Row count (large, for many entries)
      type: u2
```

**Row Index Structure** (builds backwards from end of page):
```yaml
row_group:
  instances:
    base:
      value: len_page - (group_index × 0x24)
    row_present_flags:  # Bitmap of present rows
      pos: base - 4
      type: u2
    rows:               # Row offsets (16 per group)
      type: row_ref
      repeat: expr
      repeat-expr: 16
```

#### Table Types

| Type | ID | Description |
|------|-------|-------------|
| `tracks` | 0x00 | Track metadata (title, artist, BPM, etc.) |
| `genres` | 0x01 | Genre classifications |
| `artists` | 0x02 | Artist names |
| `albums` | 0x03 | Album names |
| `labels` | 0x04 | Record labels |
| `keys` | 0x05 | Musical keys (Camelot/OpenKey) |
| `colors` | 0x06 | Track colors |
| `playlist_tree` | 0x07 | Playlist hierarchy |
| `playlist_entries` | 0x08 | Track assignments to playlists |
| `history_playlists` | 0x0B | Play history metadata |
| `history_entries` | 0x0C | History track entries |
| `artwork` | 0x0D | Album artwork paths |
| `columns` | 0x10 | Browse category definitions |

#### String Encoding

DeviceSQL menggunakan custom string format dengan tipe prefix:

**Type 0x02 - Short ASCII**:
```
┌──────────┬─────────────────────┐
│ 0x02 (1) │ Length (1) │ ASCII... │
└──────────┴─────────────────────┘
```

**Type 0x03 - Long ASCII**:
```
┌──────────┬────────────────┬─────────────┐
│ 0x03 (1) │ Length (4 LE)  │ ASCII...    │
└──────────┴────────────────┴─────────────┘
```

**Type 0x06 - UTF-16LE**:
```
┌──────────┬────────────────┬──────────────┐
│ 0x06 (1) │ Length (4 LE)  │ UTF-16LE...  │
└──────────┴────────────────┴──────────────┘
```

#### Track Row Structure

Track rows (type 0x00) memiliki struktur fixed-size dengan string offsets:

```yaml
track_row:
  seq:
    - type: u2           # Magic word (0x80, 0x00)
    - id: index_shift    # Row index
      type: u2
    - type: u4
    - id: sample_rate    # Audio sample rate
      type: u4
    - id: composer_id    # Composer artist ID
      type: u4
    - id: file_size      # File size in bytes
      type: u4
    - id: id             # Track ID
      type: u4
    - type: u4 × 4
    - id: artist_id      # Main artist ID
      type: u4
    - type: u4
    - id: remixer_id     # Remixer artist ID
      type: u4
    - id: bitrate        # Bitrate in kbps
      type: u4
    - id: track_number   # Track number
      type: u4
    - type: u4 × 2
    - id: album_id       # Album ID
      type: u4
    - id: artist_id_2    # Alternative artist ID
      type: u4
    - type: u4
    - id: disc_number    # Disc number
      type: u2
    - id: play_count     # Play count
      type: u2
    - type: u4
    - id: duration       # Duration in seconds
      type: u2
    - type: u2 × 3
    # String offsets (from row base)
    - id: ofs_strings    # Offsets to various strings
      type: u2 × 19      # title, artist, album, etc.
```

### ANLZ Format (Analysis Files)

ANLZ files menggunakan tag-based structure dengan **big-endian** byte order (berbeda dari PDB).

#### Kaitai Struct Specification

Format ANLZ mengikuti spesifikasi [rekordbox_anlz.ksy](https://github.com/Deep-Symmetry/crate-digger/blob/main/src/main/kaitai/rekordbox_anlz.ksy):

**ANLZ Header**:
```yaml
seq:
  - id: magic          # "PMAI" (big-endian)
    contents: [0x50, 0x4d, 0x41, 0x49]
  - id: len_header     # Header length (typically 28)
    type: u4be
  - id: len_file       # Total file length
    type: u4be
  - id: tags           # Tagged sections
    type: tagged_section
    repeat: eos
```

**Tagged Section**:
```yaml
tagged_section:
  seq:
    - id: fourcc       # Section type (4 chars)
      type: str
      size: 4
      encoding: ASCII
    - id: len_header   # Section header length
      type: u4be
    - id: len_tag      # Section data length
      type: u4be
    - id: body         # Section data
      size: len_tag - 12
      type:
        switch-on: fourcc
        cases:
          '"PQTZ"': beat_grid_tag
          '"PCO2"': cue_tag
          '"PCOB"': cue_extended_tag
          '"PWAV"': wave_preview_tag
          '"PWV3"': wave_color_preview_tag
          '"PWV5"': wave_color_scroll_tag
```

#### PQTZ - Beat Grid Tag

Beat grid data untuk BPM sync dan quantization:

```yaml
beat_grid_tag:
  seq:
    - type: u4be       # Unknown
    - type: u4be       # Unknown
    - id: num_beats    # Number of beat entries
      type: u4be
    - id: beats
      type: beat_grid_beat
      repeat: expr
      repeat-expr: num_beats

beat_grid_beat:
  seq:
    - id: beat_number  # Beat index (1-based)
      type: u2be
    - id: tempo        # BPM × 100 (e.g., 12800 = 128.00 BPM)
      type: u2be
    - id: time         # Time in milliseconds
      type: u4be
```

#### PCO2/PCOB - Cue Point Tags

Hot cues, memory cues, dan loops:

```yaml
cue_tag:
  seq:
    - id: type         # Cue type (1=memory, 2=hot)
      type: u4be
    - type: u4be       # Unknown
    - id: num_cues     # Number of cues
      type: u4be
    - id: memory_count # Memory cue count
      type: u4be
    - id: cues
      type: cue_entry
      repeat: expr
      repeat-expr: num_cues

cue_entry:
  seq:
    - id: magic        # "PCPT" (big-endian)
      contents: [0x50, 0x43, 0x50, 0x54]
    - id: len_header   # Entry header length
      type: u4be
    - id: len_entry    # Entry total length
      type: u4be
    - id: hot_cue      # Hot cue number (0-7)
      type: u4be
    - id: status       # Status flags
      type: u4be
    - type: u4be       # Unknown
    - id: order_first  # Order index (MSB)
      type: u2be
    - id: order_last   # Order index (LSB)
      type: u2be
    - id: type         # Cue type
      type: u1
    - type: u1 × 3     # Padding
    - id: time         # Time in milliseconds (big-endian!)
      type: u4be
    - id: loop_time    # Loop end time (0 if not loop)
      type: u4be
    - id: color_id     # Color code
      type: u1
```

#### PWV5 - RGB Waveform Data

3-band frequency waveform untuk visualisasi:

```yaml
wave_color_scroll_tag:
  seq:
    - id: len_entry_bytes  # Bytes per entry
      type: u4be
    - id: len_entries      # Number of entries
      type: u4be
    - type: u4be           # Unknown
    - id: entries
      type: waveform_entry
      repeat: expr
      repeat-expr: len_entries

waveform_entry:
  seq:
    - id: height       # Height value (0-31)
      type: u1
    - id: whiteness    # Whiteness/brightness
      type: u1
    - id: color_code   # Color code (RGB mapping)
      type: u1
```

**Color Mapping**:
- Byte 0 (height): Waveform amplitude (0-31 scale)
- Byte 1 (whiteness): Core/mid frequency intensity
- Byte 2 (color): RGB frequency distribution
  - Low frequencies (bass): Warm colors (red/orange)
  - Mid frequencies: White/neutral
  - High frequencies (treble): Cool colors (blue/cyan)

---

## Struktur Project

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
│   │   ├── KeyParser.php           # Musical key data
│   │   ├── ColorParser.php         # Color classifications
│   │   ├── LabelParser.php         # Record labels
│   │   ├── HistoryParser.php       # Play history
│   │   ├── ColumnsParser.php       # Browse category metadata
│   │   └── ArtworkParser.php       # Album artwork metadata
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
│   ├── pages/
│   │   ├── debug.php               # Debug page
│   │   └── table.php               # Table view page
│   ├── partials/
│   │   ├── head.php                # HTML head
│   │   └── footer.php              # JavaScript includes
│   ├── css/
│   │   └── main.css                # All custom styles
│   ├── index.php                   # Main entry point
│   ├── router.php                  # Clean URL routing
│   └── audio.php                   # Audio streaming endpoint
├── output/                         # Log files
└── Rekordbox-USB/                  # Rekordbox USB/SD export
    ├── PIONEER/
    │   ├── rekordbox/
    │   │   ├── export.pdb
    │   │   └── exportExt.pdb
    │   └── USBANLZ/                # Analysis files
    │       ├── P000/00000001/
    │       │   ├── ANLZ0000.DAT    # Beatgrid & basic waveform
    │       │   ├── ANLZ0000.EXT    # Extended RGB waveform (preferred)
    │       │   └── ANLZ0000.2EX    # 3-band waveform (CDJ-3000)
    └── Contents/                   # Audio files (.mp3, .flac, etc)
```

---

## Parser Implementation Details

### Core Classes

#### RekordboxReader
**File**: `src/RekordboxReader.php`

Main orchestrator yang mengkoordinasi semua parsing operations:

```php
public function run(): array {
    // 1. Parse database header dan tables
    $pdbParser = new PdbParser($this->pdbPath);
    $pdbData = $pdbParser->parse();
    
    // 2. Parse metadata tables
    $artists = $artistAlbumParser->parseArtists();
    $albums = $artistAlbumParser->parseAlbums();
    $genres = $genreParser->parseGenres();
    
    // 3. Parse tracks (linked-list traversal)
    $tracks = $trackParser->parseTracks();
    
    // 4. Parse playlists (linked-list traversal)
    $playlists = $playlistParser->parsePlaylists();
    
    // 5. Integrate ANLZ data
    $tracks = $this->integrateAnlzData($tracks);
    
    return [
        'metadata' => [...],
        'tracks' => [...],
        'playlists' => [...],
        // ... other data
    ];
}
```

#### PdbParser - Binary Database Reader
**File**: `src/Parsers/PdbParser.php`

Low-level parser untuk DeviceSQL format:

**Key Features**:
- Little-endian byte order (`V`, `v` unpack formats)
- Page-based navigation (4096 byte pages)
- String extraction dengan multiple encodings
- Table type identification

**Critical Methods**:
```php
// Read page by index
public function readPage(int $pageIndex): string {
    $offset = $this->header['page_size'] * $pageIndex;
    return substr($this->data, $offset, $this->header['page_size']);
}

// Extract DeviceSQL strings
public function extractString(string $data, int $offset): array {
    $type = ord($data[$offset]);
    
    switch ($type) {
        case 0x02: // Short ASCII
            $length = ord($data[$offset + 1]);
            $string = substr($data, $offset + 2, $length);
            return [$string, $offset + 2 + $length];
            
        case 0x03: // Long ASCII
            $length = unpack('V', substr($data, $offset + 1, 4))[1];
            $string = substr($data, $offset + 5, $length);
            return [$string, $offset + 5 + $length];
            
        case 0x06: // UTF-16LE
            $length = unpack('V', substr($data, $offset + 1, 4))[1];
            $utf16 = substr($data, $offset + 5, $length);
            $string = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16LE');
            return [$string, $offset + 5 + $length];
    }
}
```

#### TrackParser - Linked-List Traversal
**File**: `src/Parsers/TrackParser.php`

**Critical Fix**: Menggunakan linked-list traversal (bukan sequential):

```php
private function extractTrackRows($table) {
    $tracks = [];
    $firstPage = $table['first_page'];
    $lastPage = $table['last_page'];
    $expectedType = $table['type'];
    
    $currentPage = $firstPage;
    $visitedPages = [];  // Prevent infinite loops
    
    // Follow next_page links
    while ($currentPage > 0 && $currentPage <= $lastPage) {
        if (isset($visitedPages[$currentPage])) {
            break;  // Circular reference detected
        }
        $visitedPages[$currentPage] = true;
        
        $pageData = $this->pdbParser->readPage($currentPage);
        $pageHeader = unpack('Vgap/Vpage_index/Vtype/Vnext_page', 
                            substr($pageData, 0, 16));
        
        // Verify page type
        if ($pageHeader['type'] != $expectedType) {
            break;
        }
        
        // Parse tracks on this page
        $trackRows = $this->parseTrackPage($pageData, $currentPage);
        $tracks = array_merge($tracks, $trackRows);
        
        // Move to next page in linked list
        $currentPage = $pageHeader['next_page'];
    }
    
    return $tracks;
}
```

**Mengapa Linked-List Penting**:
- Pages tidak sequential: Page 1 → 2 → 51 → 61 (bukan 1 → 2 → 3 → 4)
- Sequential iteration melewatkan mayoritas data
- Linked-list traversal mengikuti `next_page` field di setiap page header

#### AnlzParser - Big-Endian Analysis Files
**File**: `src/Parsers/AnlzParser.php`

**Critical**: ANLZ files menggunakan **big-endian** (berbeda dari PDB):

```php
// Parse beat grid (PQTZ section)
private function extractBeatgrid() {
    $section = $this->sections['PQTZ'] ?? null;
    if (!$section) return [];
    
    $offset = 16;  // Skip section header
    $numBeats = unpack('N', substr($section, $offset, 4))[1];  // Big-endian!
    $offset += 4;
    
    $beats = [];
    for ($i = 0; $i < $numBeats; $i++) {
        $beatData = unpack(
            'nbeat_number/ntempo/Ntime',  // 'n' = big-endian u2, 'N' = big-endian u4
            substr($section, $offset, 8)
        );
        
        $beats[] = [
            'beat' => $beatData['beat_number'],
            'tempo' => $beatData['tempo'] / 100,  // BPM stored as × 100
            'time' => $beatData['time'] / 1000    // Convert ms to seconds
        ];
        $offset += 8;
    }
    
    return $beats;
}

// Parse cue points (PCO2 section)
private function extractCuePoints() {
    $section = $this->sections['PCO2'] ?? null;
    if (!$section) return [];
    
    $offset = 20;  // Skip section header
    $numCues = unpack('N', substr($section, $offset - 8, 4))[1];
    
    $cues = [];
    for ($i = 0; $i < $numCues; $i++) {
        // Parse PCPT entry (cue point)
        $cueData = unpack(
            'Nmagic/Nlen_header/Nlen_entry/Nhot_cue/Nstatus/' .
            'Nunknown/norder1/norder2/Ctype/C3pad/Ntime/Nloop_time/Ccolor',
            substr($section, $offset, 41)
        );
        
        if ($cueData['magic'] == 0x50435054) {  // "PCPT"
            $cues[] = [
                'hot_cue' => $cueData['hot_cue'],
                'time' => $cueData['time'] / 1000,  // Big-endian ms → seconds
                'type' => $cueData['type'],
                'color' => $cueData['color']
            ];
        }
        
        $offset += $cueData['len_entry'];
    }
    
    return $cues;
}
```

#### ArtworkParser - Album Artwork Metadata
**File**: `src/Parsers/ArtworkParser.php`

Parses artwork table (type 0x0D) to extract album art paths:

**Row Structure** (based on rekordcrate spec):
```php
artwork_row:
  - id: subtype         # Always 0x03 for artwork rows
    type: u2
  - id: index_shift     # Row index
    type: u2
  - id: artwork_id      # Artwork ID (referenced by tracks)
    type: u4
  - id: path            # Path to artwork file (DeviceSQL string)
    type: device_sql_string
```

**Key Features**:
- Validates subtype (must be 0x03)
- Creates ID-to-path mapping for track integration
- Returns both simple mapping and full artwork data
- Artwork IDs referenced in track records (offset 0x1C)

**Integration with Tracks**:
```php
// Track row references artwork
$track['artwork_id'] = 123;

// ArtworkParser resolves path
$artworkPath = $artworkParser->getArtworkPath(123);
// Returns: "AlbumArt/artwork_123.jpg"

// TrackParser integrates
$track['artwork_path'] = $artworkPath;
```

#### ColumnsParser - Browse Category Metadata
**File**: `src/Parsers/ColumnsParser.php`

Parses columns table (type 0x10) that defines browsing categories used by CDJs:

**Row Structure** (based on rekordcrate spec):
```php
column_row:
  - id: subtype         # Always 0x06 for column rows
    type: u2
  - id: index_shift     # Category index (0x00-0x12)
    type: u2
  - id: unknown         # Unknown field
    type: u2
  - id: name            # Category name (optional string)
    type: device_sql_string
```

**Column Type Mapping**:
| Index | Type | Description | CDJ Usage |
|-------|------|-------------|-----------|
| 0x00 | Track | Track name | Main browsing |
| 0x01 | Genre | Genre category | Genre filter |
| 0x02 | Artist | Artist name | Artist browsing |
| 0x03 | Album | Album name | Album view |
| 0x04 | Label | Record label | Label filter |
| 0x05 | Key | Musical key | Harmonic mixing |
| 0x06 | Rating | Star rating | Quality filter |
| 0x07 | Color | Color classification | Color sorting |
| 0x08 | Time | Duration | Time-based sorting |
| 0x09 | Bit Rate | Audio bitrate | Quality sorting |
| 0x0A | BPM | Tempo | BPM range filtering |
| 0x0B | Year | Release year | Year sorting |
| 0x0C | Comment | User comments | Notes browsing |
| 0x0D | Date Added | Import date | Recently added |
| 0x0E | Original Artist | Original artist | Remix filtering |
| 0x0F | Remixer | Remixer name | Remix browsing |
| 0x10 | Composer | Composer name | Composer filter |
| 0x11 | Album Artist | Album artist | Compilation handling |
| 0x12 | DJ Play Count | Play statistics | Popular tracks |

**Purpose**:
- Defines which metadata fields are available on CDJs/XDJs
- Controls sorting and filtering options on hardware
- Determines browse menu structure on Pioneer equipment

---

## Cara Penggunaan

### Requirements
- PHP 8.2 atau lebih tinggi
- Extension: mbstring (untuk UTF-16LE string handling)
- Browser modern dengan HTML5 Canvas support

### Menjalankan Web GUI

1. Pastikan folder `Rekordbox-USB` berisi export dari Rekordbox
2. Jalankan PHP built-in server:
   ```bash
   cd public
   php -S 0.0.0.0:5000 -t . router.php
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
1. **Set Master Deck**: Klik **MASTER** button untuk set deck sebagai master
2. **Pitch Slider**: Move slider untuk permanent tempo change (±16%)
3. **Master Tempo**: Toggle untuk lock musical key
4. **Nudge**: Hold +/- buttons untuk temporary speed adjustment
5. **BPM Sync**: Toggle on/off untuk latching sync mode
6. **Beat Sync**: One-time sync tempo + align beat grid phase

#### Hot Cues
1. Klik hot cue pad (1-8) untuk jump ke cue point
2. Enable **Quantize** (Q button) untuk snap to nearest beat
3. Cue markers terlihat pada waveform

### Code Examples

#### Basic Usage
```php
use RekordboxReader\RekordboxReader;

$exportPath = '/path/to/Rekordbox-USB';
$outputPath = '/path/to/output';

$reader = new RekordboxReader($exportPath, $outputPath, false);
$data = $reader->run();
$stats = $reader->getStats();

echo "Found " . count($data['tracks']) . " tracks\n";
echo "Found " . count($data['playlists']) . " playlists\n";
```

#### Accessing Track Data
```php
foreach ($data['tracks'] as $track) {
    echo "{$track['artist']} - {$track['title']}\n";
    echo "BPM: {$track['bpm']}, Key: {$track['key']}\n";
    
    if (!empty($track['waveform'])) {
        echo "Waveform points: " . count($track['waveform']) . "\n";
    }
    
    if (!empty($track['cue_points'])) {
        foreach ($track['cue_points'] as $cue) {
            echo "  Cue {$cue['hot_cue']}: {$cue['time']}s\n";
        }
    }
}
```

#### Building Playlist Tree
```php
function buildTree($playlists, $parentId = 0) {
    $tree = [];
    foreach ($playlists as $playlist) {
        if ($playlist['parent_id'] == $parentId) {
            $playlist['children'] = buildTree($playlists, $playlist['id']);
            $tree[] = $playlist;
        }
    }
    return $tree;
}

$tree = buildTree($data['playlists']);
```

---

## UI Design

Interface dirancang dengan gaya professional DJ software:

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
Menggunakan **FontAwesome 6.5.1** (CDN) untuk UI consistency

---

## Referensi & Resources

### Primary References

Implementasi parser ini didasarkan pada reverse-engineering work dari komunitas open source:

#### 1. **Deep Symmetry - crate-digger** (Java)
Primary reference untuk format Rekordbox database dan ANLZ files.

- **Repository**: https://github.com/Deep-Symmetry/crate-digger
- **Kaitai Specs**:
  - PDB Format: https://github.com/Deep-Symmetry/crate-digger/blob/main/src/main/kaitai/rekordbox_pdb.ksy
  - ANLZ Format: https://github.com/Deep-Symmetry/crate-digger/blob/main/src/main/kaitai/rekordbox_anlz.ksy
- **Documentation**: 
  - Main Docs: https://github.com/Deep-Symmetry/crate-digger/tree/main/doc
  - Analysis Guide: https://djl-analysis.deepsymmetry.org/rekordbox-export-analysis/exports.html
  - PDF Analysis: https://github.com/Deep-Symmetry/crate-digger/blob/main/doc/Analysis.pdf
- **Author**: James Elliott (@brunchboy)

#### 2. **Holzhaus - rekordcrate** (Rust)
Comprehensive Rust implementation dengan excellent documentation.

- **Repository**: https://github.com/holzhaus/rekordcrate
- **Documentation**:
  - Main Docs: https://holzhaus.github.io/rekordcrate/rekordcrate/index.html
  - PDB Module: https://holzhaus.github.io/rekordcrate/rekordcrate/pdb/index.html
  - ANLZ Module: https://holzhaus.github.io/rekordcrate/rekordcrate/anlz/index.html
  - Settings Module: https://holzhaus.github.io/rekordcrate/rekordcrate/setting/index.html
  - Utils Module: https://holzhaus.github.io/rekordcrate/rekordcrate/util/index.html
- **Author**: Jan Holthuis (@Holzhaus)

#### 3. **Mixxx DJ Software** (C++)
Open source DJ application dengan production-ready Rekordbox support.

- **Repository**: https://github.com/mixxxdj/mixxx
- **Rekordbox Metadata Library**: https://github.com/mixxxdj/mixxx/tree/main/lib/rekordbox-metadata
- **Kaitai Specs**:
  - PDB Format: https://github.com/mixxxdj/mixxx/blob/main/lib/rekordbox-metadata/rekordbox_pdb.ksy
  - ANLZ Format: https://github.com/mixxxdj/mixxx/blob/main/lib/rekordbox-metadata/rekordbox_anlz.ksy
  - Settings Format: https://github.com/mixxxdj/mixxx/blob/main/lib/rekordbox-metadata/rekordbox_setting.ksy
- **Website**: https://mixxx.org
- **UI Design Inspiration**: MIXXX LateNight skin

#### 4. **Henry Betts - Rekordbox Decoding** (C#)
Original reverse-engineering work yang menjadi foundation banyak implementasi.

- **Repository**: https://github.com/henrybetts/Rekordbox-Decoding
- **Documentation**: https://github.com/henrybetts/Rekordbox-Decoding/blob/master/README.md
- **Author**: Henry Betts (@henrybetts)

#### 5. **Digital DJ Tools - DJ Data Converter** (TypeScript/Node.js)
Modern implementation untuk DJ library conversion.

- **Repository**: https://github.com/digital-dj-tools/dj-data-converter
- **Focus**: Format conversion between Rekordbox, Serato, Traktor

### Technical Specifications

#### Kaitai Struct
Format specification language untuk binary data:
- **Website**: https://kaitai.io
- **Documentation**: https://doc.kaitai.io
- **Web IDE**: https://ide.kaitai.io (untuk visualize binary structures)

#### File Format Details

**DeviceSQL (PDB)**:
- Proprietary SQLite-like database format
- Page-based structure (4096 bytes)
- Little-endian byte order
- Custom string encoding (ASCII, UTF-16LE)
- Linked-list page navigation via `next_page` field

**ANLZ Files**:
- Tag-based binary format
- Big-endian byte order (critical!)
- Sections: PQTZ (beatgrid), PCO2 (cues), PWV5 (waveform)
- File priority: `.EXT` > `.DAT` > `.2EX`

### Community Resources

#### Forums & Discussions
- **MIXXX Forums**: https://mixxx.discourse.group
- **Pioneer DJ Forums**: https://forums.pioneerdj.com
- **Reddit r/DJs**: https://reddit.com/r/DJs

#### Related Projects
- **Python ProDJ Link** (Fabian Lesniak): Network protocol analysis
- **Traktor Bible**: Traktor format documentation
- **Serato DJ Tools**: Serato format reverse-engineering

---

## Error Handling

### Common Issues

**Empty Waveforms**:
- Check if ANLZ files exist
- Verify file priority (`.EXT` > `.DAT` > `.2EX`)
- Some tracks may not have waveform data

**Missing Cue Points**:
- Not all tracks have hot cues set
- Check PCO2 sections in ANLZ files
- Rekordbox may not have analyzed the track

**Incorrect Beat Grid**:
- Ensure using big-endian unpacking for ANLZ
- PQTZ section contains beat grid data
- Track must be analyzed in Rekordbox

**Playlist Count Mismatch**:
- Parser now uses linked-list traversal
- No arbitrary page limits (supports unlimited pages)
- Circular reference detection prevents infinite loops

---

## Performance Considerations

**Optimization Tips**:

1. **Disable Verbose Logging**: Set `$verbose = false` for production
2. **Cache Parsed Data**: Store results to avoid re-parsing
3. **Selective Parsing**: Only parse needed data types
4. **Memory Usage**: Large libraries (10k+ tracks) may require increased PHP memory

**Benchmarks** (approximate):
- 1,000 tracks: ~2-3 seconds
- 5,000 tracks: ~8-12 seconds
- 10,000 tracks: ~20-30 seconds

---

## License

Project ini dibuat untuk tujuan educational dan interoperability dengan DJ equipment ecosystem.

## Credits

### Core Contributors
- **Henry Betts** (@henrybetts) - Original Rekordbox Decoding (C#)
- **Fabian Lesniak** (@flesniak) - Python ProDJ Link
- **James Elliott** (@brunchboy) - Deep Symmetry / Crate Digger (Java)
- **Jan Holthuis** (@Holzhaus) - Rekordcrate (Rust)
- **MIXXX Development Team** - Open source DJ software & UI/UX inspiration
- **GreyCat** - Kaitai Struct development assistance

### Special Thanks

Terima kasih kepada seluruh komunitas yang berkontribusi dalam reverse-engineering format Rekordbox untuk mendukung interoperability di ekosistem DJ software. Tanpa kerja keras mereka, project ini tidak akan mungkin terwujud.

---

**v2.1 - Enhanced Parser & Smooth Waveforms** | Powered by PHP 8.2 | November 2025
