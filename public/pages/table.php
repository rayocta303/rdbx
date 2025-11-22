<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../src/RekordboxReader.php';
require_once __DIR__ . '/../../src/Parsers/PdbParser.php';
require_once __DIR__ . '/../../src/Parsers/TrackParser.php';
require_once __DIR__ . '/../../src/Parsers/ArtistAlbumParser.php';
require_once __DIR__ . '/../../src/Parsers/GenreParser.php';
require_once __DIR__ . '/../../src/Parsers/KeyParser.php';

use RekordboxReader\RekordboxReader;
use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Parsers\ArtistAlbumParser;
use RekordboxReader\Parsers\GenreParser;
use RekordboxReader\Parsers\KeyParser;

$error = null;
$pdbHeader = [];
$tracks = [];
$playlists = [];
$playlistEntries = [];
$artistsData = [];
$albumsData = [];
$genresData = [];
$keysData = [];
$colorsData = [];
$labelsData = [];
$artworkData = [];
$historyData = [];
$columnsData = [];
$tablesInfo = [];

try {
    $exportPath = __DIR__ . '/../../Rekordbox-USB';
    $pdbPath = $exportPath . '/PIONEER/rekordbox/export.pdb';
    
    if (is_dir($exportPath) && file_exists($pdbPath)) {
        $pdbParser = new PdbParser($pdbPath);
        $pdbData = $pdbParser->parse();
        $pdbHeader = $pdbData['header'];
        $tablesInfo = $pdbData['tables'];
        
        $reader = new RekordboxReader($exportPath, __DIR__ . '/../../output', false);
        $data = $reader->run();
        
        $tracks = $data['tracks'] ?? [];
        $playlists = $data['playlists'] ?? [];
        
        // Extract playlist entries
        foreach ($playlists as $playlist) {
            if (!empty($playlist['entries']) && is_array($playlist['entries'])) {
                foreach ($playlist['entries'] as $trackId) {
                    $playlistEntries[] = [
                        'playlist_id' => $playlist['id'],
                        'playlist_name' => $playlist['name'],
                        'track_id' => $trackId
                    ];
                }
            }
        }
        
        $artistParser = new ArtistAlbumParser($pdbParser);
        $artists = $artistParser->parseArtists();
        foreach ($artists as $id => $name) {
            if (is_numeric($id)) {
                $artistsData[] = ['id' => $id, 'name' => $name];
            }
        }
        
        $albums = $artistParser->parseAlbums();
        foreach ($albums as $id => $name) {
            if (is_numeric($id)) {
                $albumsData[] = ['id' => $id, 'name' => $name];
            }
        }
        
        $genreParser = new GenreParser($pdbParser);
        $genres = $genreParser->parseGenres();
        foreach ($genres as $id => $name) {
            if (is_numeric($id)) {
                $genresData[] = ['id' => $id, 'name' => $name];
            }
        }
        
        $keyParser = new KeyParser($pdbParser);
        $keys = $keyParser->parseKeys();
        foreach ($keys as $id => $name) {
            $keysData[] = ['id' => $id, 'name' => $name];
        }
        
        // Parse Colors, Labels, Artwork directly from PDB tables
        $colorsTable = $pdbParser->getTable(PdbParser::TABLE_COLORS);
        if ($colorsTable) {
            for ($pageIdx = $colorsTable['first_page']; $pageIdx <= $colorsTable['last_page']; $pageIdx++) {
                $pageData = $pdbParser->readPage($pageIdx);
                if ($pageData && strlen($pageData) >= 40) {
                    $offset = 40;
                    while ($offset + 20 < strlen($pageData)) {
                        $testId = unpack('v', substr($pageData, $offset, 2))[1];
                        if ($testId > 0 && $testId < 256) {
                            list($str, $newOff) = $pdbParser->extractString($pageData, $offset + 4);
                            if ($str) {
                                $colorsData[] = ['id' => $testId, 'name' => trim($str)];
                                $offset = $newOff;
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
        }
        
        $labelsTable = $pdbParser->getTable(PdbParser::TABLE_LABELS);
        if ($labelsTable) {
            for ($pageIdx = $labelsTable['first_page']; $pageIdx <= $labelsTable['last_page']; $pageIdx++) {
                $pageData = $pdbParser->readPage($pageIdx);
                if ($pageData && strlen($pageData) >= 40) {
                    $offset = 40;
                    while ($offset + 20 < strlen($pageData)) {
                        $testId = unpack('v', substr($pageData, $offset, 2))[1];
                        if ($testId > 0 && $testId < 10000) {
                            list($str, $newOff) = $pdbParser->extractString($pageData, $offset + 4);
                            if ($str) {
                                $labelsData[] = ['id' => $testId, 'name' => trim($str)];
                                $offset = $newOff;
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
        }
        
        $artworkTable = $pdbParser->getTable(PdbParser::TABLE_ARTWORK);
        if ($artworkTable) {
            for ($pageIdx = $artworkTable['first_page']; $pageIdx <= $artworkTable['last_page']; $pageIdx++) {
                $pageData = $pdbParser->readPage($pageIdx);
                if ($pageData && strlen($pageData) >= 40) {
                    $offset = 40;
                    while ($offset + 20 < strlen($pageData)) {
                        $testId = unpack('V', substr($pageData, $offset, 4))[1];
                        if ($testId > 0 && $testId < 100000) {
                            list($str, $newOff) = $pdbParser->extractString($pageData, $offset + 4);
                            if ($str) {
                                $artworkData[] = ['id' => $testId, 'path' => trim($str)];
                                $offset = $newOff;
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
        }
        
        // Note: History, Columns tables require complex parsing - mark as unparsed for now
        $columnsData = [['note' => 'Columns table parsing not yet implemented']];
        $historyData = [['note' => 'History table parsing not yet implemented']];
        
    } else {
        $error = "Rekordbox-USB directory or export.pdb not found!";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/../partials/head.php';
?>

<div class="container mx-auto px-2 max-w-full">
    <div class="mixxx-container rounded-lg mb-6">
        <div class="mixxx-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <i class="fas fa-database text-4xl text-cyan-400"></i>
                    <div>
                        <h1 class="text-3xl font-bold deck-title">Complete Database View</h1>
                        <p class="text-gray-400 mt-1 text-sm">Comprehensive export.pdb Table Browser - All Tables & Fields</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="p-6">
            <div class="bg-red-900 bg-opacity-30 border border-red-500 rounded-lg p-4">
                <h3 class="text-red-400 font-semibold mb-2"><i class="fas fa-times-circle"></i> Error</h3>
                <p class="text-red-300"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
        <?php else: ?>
        
        <div class="border-b-2 border-cyan-600">
            <nav class="flex -mb-px overflow-x-auto">
                <button onclick="showTab('overview')" id="overviewTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-cyan-500 text-cyan-400 bg-gray-800 whitespace-nowrap">
                    <i class="fas fa-info"></i> Overview
                </button>
                <button onclick="showTab('tracks')" id="tracksTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-music"></i> Tracks (<?= count($tracks) ?>)
                </button>
                <button onclick="showTab('playlists')" id="playlistsTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-list"></i> Playlists (<?= count($playlists) ?>)
                </button>
                <button onclick="showTab('entries')" id="entriesTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-list-ol"></i> Playlist Entries (<?= count($playlistEntries) ?>)
                </button>
                <button onclick="showTab('artists')" id="artistsTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-user"></i> Artists (<?= count($artistsData) ?>)
                </button>
                <button onclick="showTab('albums')" id="albumsTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-compact-disc"></i> Albums (<?= count($albumsData) ?>)
                </button>
                <button onclick="showTab('genres')" id="genresTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-guitar"></i> Genres (<?= count($genresData) ?>)
                </button>
                <button onclick="showTab('keys')" id="keysTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-key"></i> Keys (<?= count($keysData) ?>)
                </button>
                <button onclick="showTab('colors')" id="colorsTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-palette"></i> Colors (<?= count($colorsData) ?>)
                </button>
                <button onclick="showTab('labels')" id="labelsTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-tag"></i> Labels (<?= count($labelsData) ?>)
                </button>
                <button onclick="showTab('artwork')" id="artworkTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-image"></i> Artwork (<?= count($artworkData) ?>)
                </button>
                <button onclick="showTab('history')" id="historyTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-clock"></i> History
                </button>
                <button onclick="showTab('columns')" id="columnsTab" class="tab-button px-4 py-3 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-cyan-400 whitespace-nowrap">
                    <i class="fas fa-columns"></i> Columns
                </button>
            </nav>
        </div>

        <!-- Database Overview -->
        <div id="overviewContent" class="tab-content p-6">
            <h2 class="text-2xl font-bold deck-title mb-4">Database Overview</h2>
            
            <div class="bg-gray-900 rounded-lg p-6 border border-gray-700 mb-6">
                <h3 class="text-xl font-bold text-cyan-400 mb-4"><i class="fas fa-file-alt"></i> PDB Header</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div><span class="text-gray-500 text-sm">Page Size:</span><div class="text-white font-mono"><?= $pdbHeader['page_size'] ?? 'N/A' ?> bytes</div></div>
                    <div><span class="text-gray-500 text-sm">Total Tables:</span><div class="text-cyan-400 font-mono"><?= $pdbHeader['num_tables'] ?? 'N/A' ?></div></div>
                    <div><span class="text-gray-500 text-sm">Sequence:</span><div class="text-purple-400 font-mono"><?= $pdbHeader['sequence'] ?? 'N/A' ?></div></div>
                </div>
            </div>

            <div class="bg-gray-900 rounded-lg p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-cyan-400 mb-4"><i class="fas fa-table"></i> All Tables</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">Type</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                                <th class="py-3 px-3 text-left text-cyan-400">First</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Last</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Pages</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tablesInfo as $table): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= sprintf('0x%02X', $table['type']) ?></td>
                                <td class="py-2 px-3 text-white font-semibold"><?= htmlspecialchars($table['type_name']) ?></td>
                                <td class="py-2 px-3 font-mono text-gray-400"><?= $table['first_page'] ?></td>
                                <td class="py-2 px-3 font-mono text-gray-400"><?= $table['last_page'] ?></td>
                                <td class="py-2 px-3 text-cyan-400"><?= ($table['last_page'] - $table['first_page'] + 1) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tracks - COMPLETE WITH ALL FIELDS -->
        <div id="tracksContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Complete Tracks Table - All Fields</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-2 px-2 text-left text-cyan-400">ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Title</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Artist</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Artist ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Album</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Album ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Genre</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Genre ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Key</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Key ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">BPM</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Duration</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Year</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Track#</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Rating</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Color ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Artwork ID</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Bitrate</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Sample Rate</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Play Count</th>
                                <th class="py-2 px-2 text-left text-cyan-400">File Size</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Comment</th>
                                <th class="py-2 px-2 text-left text-cyan-400">File Path</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Analyze Path</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracks as $track): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-1 px-2 font-mono text-purple-400"><?= $track['id'] ?></td>
                                <td class="py-1 px-2 text-white max-w-xs truncate"><?= htmlspecialchars($track['title'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-cyan-300"><?= htmlspecialchars($track['artist'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['artist_id'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-300"><?= htmlspecialchars($track['album'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['album_id'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= htmlspecialchars($track['genre'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['genre_id'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-orange-400"><?= htmlspecialchars($track['key'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['key_id'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-green-400"><?= $track['bpm'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= gmdate("i:s", $track['duration'] ?? 0) ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= $track['year'] ?? '-' ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['track_number'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-yellow-400"><?= $track['rating'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['color_id'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono"><?= $track['artwork_id'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= $track['bitrate'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= number_format($track['sample_rate'] ?? 0) ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= $track['play_count'] ?? 0 ?></td>
                                <td class="py-1 px-2 text-gray-400"><?= number_format($track['file_size'] ?? 0) ?></td>
                                <td class="py-1 px-2 text-gray-500 max-w-xs truncate"><?= htmlspecialchars($track['comment'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono text-xs max-w-md truncate" title="<?= htmlspecialchars($track['file_path'] ?? '') ?>"><?= htmlspecialchars($track['file_path'] ?? '-') ?></td>
                                <td class="py-1 px-2 text-gray-500 font-mono text-xs max-w-xs truncate" title="<?= htmlspecialchars($track['analyze_path'] ?? '') ?>"><?= htmlspecialchars($track['analyze_path'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Playlists -->
        <div id="playlistsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Playlists</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Parent ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Is Folder</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Tracks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playlists as $playlist): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= $playlist['id'] ?></td>
                                <td class="py-2 px-3 text-white"><?= htmlspecialchars($playlist['name']) ?></td>
                                <td class="py-2 px-3 text-gray-400"><?= $playlist['parent_id'] ?? '-' ?></td>
                                <td class="py-2 px-3"><?= $playlist['is_folder'] ? '<span class="text-yellow-400">Yes</span>' : '<span class="text-gray-500">No</span>' ?></td>
                                <td class="py-2 px-3 text-cyan-400"><?= count($playlist['entries'] ?? []) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Playlist Entries -->
        <div id="entriesContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Playlist Entries</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">Playlist ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Playlist Name</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Track ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playlistEntries as $entry): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= $entry['playlist_id'] ?></td>
                                <td class="py-2 px-3 text-white"><?= htmlspecialchars($entry['playlist_name']) ?></td>
                                <td class="py-2 px-3 font-mono text-cyan-400"><?= $entry['track_id'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Artists -->
        <div id="artistsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Artists</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($artistsData as $artist): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= $artist['id'] ?></td>
                                <td class="py-2 px-3 text-cyan-300"><?= htmlspecialchars($artist['name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Albums -->
        <div id="albumsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Albums</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($albumsData as $album): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= $album['id'] ?></td>
                                <td class="py-2 px-3 text-white"><?= htmlspecialchars($album['name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Genres -->
        <div id="genresContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Genres</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($genresData as $genre): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= $genre['id'] ?></td>
                                <td class="py-2 px-3 text-green-400"><?= htmlspecialchars($genre['name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Keys -->
        <div id="keysContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Musical Keys</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Key</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keysData as $key): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-2 px-3 font-mono text-purple-400"><?= $key['id'] ?></td>
                                <td class="py-2 px-3 text-orange-400 font-semibold"><?= htmlspecialchars($key['name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Colors -->
        <div id="colorsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Colors</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($colorsData)): ?>
                            <tr><td colspan="2" class="py-4 px-3 text-center text-gray-500">No data available</td></tr>
                            <?php else: ?>
                                <?php foreach ($colorsData as $color): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800">
                                    <td class="py-2 px-3 font-mono text-purple-400"><?= $color['id'] ?></td>
                                    <td class="py-2 px-3 text-white"><?= htmlspecialchars($color['name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Labels -->
        <div id="labelsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Labels</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($labelsData)): ?>
                            <tr><td colspan="2" class="py-4 px-3 text-center text-gray-500">No data available</td></tr>
                            <?php else: ?>
                                <?php foreach ($labelsData as $label): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800">
                                    <td class="py-2 px-3 font-mono text-purple-400"><?= $label['id'] ?></td>
                                    <td class="py-2 px-3 text-white"><?= htmlspecialchars($label['name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Artwork -->
        <div id="artworkContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Artwork</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Path</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($artworkData)): ?>
                            <tr><td colspan="2" class="py-4 px-3 text-center text-gray-500">No data available</td></tr>
                            <?php else: ?>
                                <?php foreach ($artworkData as $artwork): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800">
                                    <td class="py-2 px-3 font-mono text-purple-400"><?= $artwork['id'] ?></td>
                                    <td class="py-2 px-3 text-gray-300 font-mono text-xs"><?= htmlspecialchars($artwork['path']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- History -->
        <div id="historyContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">History</h2>
            <div class="bg-gray-900 rounded-lg p-6 border border-gray-700">
                <div class="bg-yellow-900 bg-opacity-30 border border-yellow-600 rounded p-4">
                    <h3 class="text-yellow-400 font-semibold mb-2"><i class="fas fa-exclamation-triangle"></i> Not Implemented</h3>
                    <p class="text-yellow-300 text-sm">History table parsing requires complex structure analysis and is not yet implemented.</p>
                </div>
            </div>
        </div>

        <!-- Columns -->
        <div id="columnsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Columns</h2>
            <div class="bg-gray-900 rounded-lg p-6 border border-gray-700">
                <div class="bg-yellow-900 bg-opacity-30 border border-yellow-600 rounded p-4">
                    <h3 class="text-yellow-400 font-semibold mb-2"><i class="fas fa-exclamation-triangle"></i> Not Implemented</h3>
                    <p class="text-yellow-300 text-sm">Columns table parsing requires complex structure analysis and is not yet implemented.</p>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    document.getElementById(tabName + 'Content').classList.remove('hidden');
    document.getElementById(tabName + 'Tab').classList.remove('border-transparent', 'text-gray-500');
    document.getElementById(tabName + 'Tab').classList.add('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
}
</script>
</body>
</html>
