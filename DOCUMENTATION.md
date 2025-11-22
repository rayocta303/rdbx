# Rekordbox Export Reader - Documentation

Complete source code documentation for the Rekordbox Export Reader project.

## Architecture Overview

The application uses a modular parser-based architecture to read and process Rekordbox USB export files:

- **Main Orchestrator**: `RekordboxReader` coordinates all parsing operations
- **Core Parser**: `PdbParser` handles binary database file reading
- **Specialized Parsers**: Individual parsers for different data types (tracks, playlists, ANLZ files, etc.)
- **Utilities**: Logger and helper functions

## Core Classes

### RekordboxReader

**File**: `src/RekordboxReader.php`

**Purpose**: Main orchestrator class that coordinates all parsing operations and aggregates data from different sources.

**Key Methods**:

- `__construct(string $exportPath, string $outputPath, bool $verbose)` - Initialize reader with paths
- `run(): array` - Execute complete parsing pipeline and return aggregated data
- `getStats(): array` - Return parsing statistics (timing, counts, errors)

**Data Flow**:
1. Initialize PDB parser
2. Parse database metadata
3. Extract tracks, playlists, artists, albums, genres, keys
4. Parse ANLZ files for waveforms and cue points
5. Aggregate and return all data

**Return Structure**:
```php
[
    'metadata' => [...],
    'tracks' => [...],
    'playlists' => [...],
    'artists' => [...],
    'albums' => [...],
    'genres' => [...],
    'keys' => [...]
]
```

---

## Parsers

### PdbParser

**File**: `src/Parsers/PdbParser.php`

**Purpose**: Low-level parser for Pioneer Database (.pdb) files using DeviceSQL format.

**Key Features**:
- Binary file reading with little-endian byte order
- Page-based database structure (4096 byte pages)
- String extraction (short ASCII, long ASCII, UTF-16LE)
- Table type identification and navigation

**Important Constants**:
```php
TABLE_TRACKS = 0x00
TABLE_GENRES = 0x01
TABLE_ARTISTS = 0x02
TABLE_ALBUMS = 0x03
TABLE_LABELS = 0x04
TABLE_KEYS = 0x05
TABLE_COLORS = 0x06
TABLE_PLAYLIST_TREE = 0x07
TABLE_PLAYLIST_ENTRIES = 0x08
TABLE_ARTWORK = 0x0D
```

**Key Methods**:
- `parse(): array` - Parse database header and table metadata
- `getTable(int $type): ?array` - Get table information by type
- `readPage(int $pageIndex): string` - Read raw page data
- `extractString(string $data, int $offset): array` - Extract string from binary data

**String Formats**:
1. **Short ASCII**: Type 0x02, 1-byte length + ASCII data
2. **Long ASCII**: Type 0x03, 4-byte length + ASCII data
3. **UTF-16LE**: Type 0x06, 4-byte length + UTF-16LE data

---

### TrackParser

**File**: `src/Parsers/TrackParser.php`

**Purpose**: Extract track metadata from the tracks table.

**Parsed Fields**:
- Basic: ID, title, artist, album, genre
- Audio: duration, BPM, bitrate, sample rate
- Metadata: year, track number, rating, key
- Files: file path, analyze path, file size
- References: artist_id, album_id, genre_id, key_id, color_id, artwork_id

**Key Methods**:
- `parseTracks(): array` - Parse all tracks from database
- `parseTrackRow(string $rowData, int $offset): array` - Parse single track entry

**Data Structure**:
```php
[
    'id' => int,
    'title' => string,
    'artist' => string,
    'album' => string,
    'bpm' => float,
    'duration' => int,
    'key' => string,
    'file_path' => string,
    // ... other fields
]
```

---

### PlaylistParser

**File**: `src/Parsers/PlaylistParser.php`

**Purpose**: Parse playlist hierarchy and track assignments.

**Features**:
- Folder structure support
- Parent-child relationships
- Track entry lists
- Corruption detection

**Key Methods**:
- `parsePlaylists(): array` - Parse all playlists with hierarchy
- `parsePlaylistEntries(int $playlistId): array` - Get track IDs for playlist

**Data Structure**:
```php
[
    'id' => int,
    'name' => string,
    'parent_id' => int,
    'is_folder' => bool,
    'entries' => [track_ids...],
    'track_count' => int
]
```

---

### AnlzParser

**File**: `src/Parsers/AnlzParser.php`

**Purpose**: Parse Rekordbox ANLZ files for waveforms, beat grids, and cue points.

**File Types**:
- `.DAT` - Basic waveform and beatgrid
- `.EXT` - Extended RGB waveform (preferred)
- `.2EX` - 3-band waveform (CDJ-3000, often empty)

**Important**: ANLZ files use **big-endian** byte order (unlike PDB files)

**Parsed Data**:
1. **PQTZ** - Beat grid data (beat positions, tempo, timing)
2. **PCOB/PCO2** - Cue points and loops
3. **PWAV/PWV3/PWV5** - Waveform data (monochrome & RGB)

**Key Methods**:
- `parseAnlzFile(string $trackId): ?array` - Parse ANLZ file for track
- `parseWaveform(string $data): array` - Extract waveform RGB data
- `parseCuePoints(string $data): array` - Extract cue point positions
- `parseBeatGrid(string $data): array` - Extract beat grid information

**Waveform Data Structure**:
```php
[
    'waveform' => [
        ['r' => int, 'g' => int, 'b' => int],
        // ... RGB values for each waveform point
    ],
    'cue_points' => [
        ['time' => float, 'type' => string, 'color' => int],
        // ... cue point data
    ],
    'beat_grid' => [
        ['beat' => int, 'time' => float, 'tempo' => float],
        // ... beat grid entries
    ]
]
```

---

### ArtistAlbumParser

**File**: `src/Parsers/ArtistAlbumParser.php`

**Purpose**: Extract artist and album data from respective tables.

**Key Methods**:
- `parseArtists(): array` - Parse all artists
- `parseAlbums(): array` - Parse all albums

**Return Format**: `[id => name]` mapping

---

### GenreParser

**File**: `src/Parsers/GenreParser.php`

**Purpose**: Extract genre classifications.

**Key Methods**:
- `parseGenres(): array` - Parse all genres

**Return Format**: `[id => name]` mapping

---

### KeyParser

**File**: `src/Parsers/KeyParser.php`

**Purpose**: Extract musical key information.

**Key Methods**:
- `parseKeys(): array` - Parse all musical keys

**Return Format**: `[id => name]` mapping (e.g., "1A", "5B", "Cm", "G major")

---

### ColorParser

**File**: `src/Parsers/ColorParser.php`

**Purpose**: Extract track color classifications.

**Key Methods**:
- `parseColors(): array` - Parse all colors

**Return Format**: `[id => name]` mapping

---

### LabelParser

**File**: `src/Parsers/LabelParser.php`

**Purpose**: Extract record label information.

**Key Methods**:
- `parseLabels(): array` - Parse all labels

**Return Format**: `[id => name]` mapping

---

### HistoryParser

**File**: `src/Parsers/HistoryParser.php`

**Purpose**: Extract play history and history playlists.

**Key Methods**:
- `parseHistoryPlaylists(): array` - Parse history playlist metadata
- `parseHistoryEntries(): array` - Parse history track entries

**Data Structure**:
```php
[
    'playlists' => [id => name],
    'entries' => [playlist_id => [track_ids]]
]
```

---

### ColumnsParser

**File**: `src/Parsers/ColumnsParser.php`

**Purpose**: Extract column layout and display settings.

**Key Methods**:
- `parseColumns(): array` - Parse column configuration

**Return Format**: Array of column settings with visibility and order information

---

## Utilities

### Logger

**File**: `src/Utils/Logger.php`

**Purpose**: Simple logging utility for debugging and tracking parsing operations.

**Key Methods**:
- `log(string $message, string $level = 'INFO')` - Write log message
- `error(string $message)` - Log error
- `warning(string $message)` - Log warning
- `debug(string $message)` - Log debug information

**Log Levels**: INFO, WARNING, ERROR, DEBUG

**Output**: Writes to file in `output/` directory with timestamps

---

## Data Format Details

### Binary Data Reading

**PDB Files (Little-Endian)**:
```php
$value = unpack('V', $data)[1];  // 32-bit unsigned little-endian
$value = unpack('v', $data)[1];  // 16-bit unsigned little-endian
```

**ANLZ Files (Big-Endian)**:
```php
$value = unpack('N', $data)[1];  // 32-bit unsigned big-endian
$value = unpack('n', $data)[1];  // 16-bit unsigned big-endian
```

### String Encoding

1. **Short ASCII** (Type 0x02):
   - 1 byte: string length
   - N bytes: ASCII characters

2. **Long ASCII** (Type 0x03):
   - 4 bytes: string length (little-endian)
   - N bytes: ASCII characters

3. **UTF-16LE** (Type 0x06):
   - 4 bytes: string length (little-endian)
   - N bytes: UTF-16LE encoded characters
   - Requires `mb_convert_encoding()` to UTF-8

---

## Usage Examples

### Basic Usage

```php
use RekordboxReader\RekordboxReader;

$exportPath = '/path/to/Rekordbox-USB';
$outputPath = '/path/to/output';
$verbose = true;

$reader = new RekordboxReader($exportPath, $outputPath, $verbose);
$data = $reader->run();
$stats = $reader->getStats();

echo "Found " . count($data['tracks']) . " tracks\n";
echo "Found " . count($data['playlists']) . " playlists\n";
```

### Accessing Track Data

```php
foreach ($data['tracks'] as $track) {
    echo "{$track['artist']} - {$track['title']}\n";
    echo "BPM: {$track['bpm']}, Key: {$track['key']}\n";
    
    if (!empty($track['waveform'])) {
        echo "Waveform points: " . count($track['waveform']) . "\n";
    }
    
    if (!empty($track['cue_points'])) {
        echo "Cue points: " . count($track['cue_points']) . "\n";
    }
}
```

### Accessing Playlist Hierarchy

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

## Error Handling

All parsers implement graceful error handling:

1. **Missing Files**: Returns empty arrays instead of failing
2. **Corrupted Data**: Logs warnings and skips corrupted entries
3. **Invalid Formats**: Falls back to safe defaults
4. **Encoding Issues**: Handles mixed encodings (ASCII, UTF-16LE)

### Common Issues

**Empty Waveforms**:
- Check if ANLZ files exist
- Verify file priority (.EXT > .DAT > .2EX)
- Some tracks may not have waveform data

**Missing Cue Points**:
- Not all tracks have hot cues set
- Check PCOB/PCO2 sections in ANLZ files

**Incorrect Beat Grid**:
- Ensure using big-endian unpacking for ANLZ
- PQTZ section contains beat grid data

---

## Performance Considerations

**Optimization Tips**:

1. **Disable Verbose Logging**: Set `$verbose = false` for production
2. **Cache Parsed Data**: Store results to avoid re-parsing
3. **Selective Parsing**: Only parse needed data types
4. **Memory Usage**: Large libraries (10k+ tracks) may require increased PHP memory

**Benchmarks** (approximate):
- 1000 tracks: ~2-3 seconds
- 5000 tracks: ~8-12 seconds
- 10000 tracks: ~20-30 seconds

---

## File Structure Reference

```
Rekordbox-USB/
├── PIONEER/
│   ├── rekordbox/
│   │   ├── export.pdb         # Main database
│   │   └── exportExt.pdb      # Extended database
│   ├── USBANLZ/               # Analysis files
│   │   └── P{XXX}/{TrackID}/
│   │       ├── ANLZ0000.DAT   # Beatgrid, basic waveform
│   │       ├── ANLZ0000.EXT   # Extended RGB waveform
│   │       └── ANLZ0000.2EX   # 3-band waveform (CDJ-3000)
│   └── Artwork/               # Album artwork
└── Contents/                  # Audio files (.mp3, .flac, etc)
```

---

## Contributing

When extending the parser:

1. Follow existing class structure
2. Use PdbParser for database access
3. Implement error handling
4. Add logging for debugging
5. Document binary format offsets
6. Include usage examples

---

## References

- [Deep Symmetry - Crate Digger](https://github.com/Deep-Symmetry/crate-digger)
- [Holzhaus - Rekordcrate](https://github.com/holzhaus/rekordcrate)
- [Henry Betts - Rekordbox Decoding](https://github.com/henrybetts/Rekordbox-Decoding)

---

**Last Updated**: v2.1 - Enhanced parsing with industrial clean architecture
