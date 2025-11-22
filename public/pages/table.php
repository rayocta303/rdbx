<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../src/RekordboxReader.php';
require_once __DIR__ . '/../../src/Parsers/PdbParser.php';
require_once __DIR__ . '/../../src/Parsers/TrackParser.php';
require_once __DIR__ . '/../../src/Parsers/ArtistAlbumParser.php';
require_once __DIR__ . '/../../src/Parsers/GenreParser.php';
require_once __DIR__ . '/../../src/Parsers/KeyParser.php';
require_once __DIR__ . '/../../src/Parsers/ColorParser.php';
require_once __DIR__ . '/../../src/Parsers/LabelParser.php';
require_once __DIR__ . '/../../src/Parsers/HistoryParser.php';
require_once __DIR__ . '/../../src/Parsers/ColumnsParser.php';
require_once __DIR__ . '/../../src/Parsers/ArtworkParser.php';

use RekordboxReader\RekordboxReader;
use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Parsers\ArtistAlbumParser;
use RekordboxReader\Parsers\GenreParser;
use RekordboxReader\Parsers\KeyParser;
use RekordboxReader\Parsers\ColorParser;
use RekordboxReader\Parsers\LabelParser;
use RekordboxReader\Parsers\HistoryParser;
use RekordboxReader\Parsers\ColumnsParser;
use RekordboxReader\Parsers\ArtworkParser;

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
        
        $colorParser = new ColorParser($pdbParser);
        $colors = $colorParser->parseColors();
        foreach ($colors as $id => $name) {
            $colorsData[] = ['id' => $id, 'name' => $name];
        }
        
        $labelParser = new LabelParser($pdbParser);
        $labels = $labelParser->parseLabels();
        foreach ($labels as $id => $name) {
            $labelsData[] = ['id' => $id, 'name' => $name];
        }
        
        $artworkParser = new ArtworkParser($pdbParser);
        $artworks = $artworkParser->parseArtwork();
        foreach ($artworks as $id => $path) {
            $artworkData[] = ['id' => $id, 'path' => trim($path)];
        }
        
        $historyParser = new HistoryParser($pdbParser);
        $historyPlaylists = $historyParser->parseHistoryPlaylists();
        $historyEntries = $historyParser->parseHistoryEntries();
        
        foreach ($historyPlaylists as $id => $name) {
            $entryCount = isset($historyEntries[$id]) ? count($historyEntries[$id]) : 0;
            $historyData[] = [
                'id' => $id,
                'name' => $name,
                'entry_count' => $entryCount
            ];
        }
        
        $columnsParser = new ColumnsParser($pdbParser);
        $columns = $columnsParser->parseColumns();
        $columnsData = $columns;
        
    } else {
        $error = "Rekordbox-USB directory or export.pdb not found!";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/../partials/head.php';
?>

<div class="container mx-auto px-2 max-w-full">
    <div class="app-container rounded-lg mb-6">
        <div class="app-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-table text-2xl text-cyan-400"></i>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Database Tables</h1>
                        <p class="text-gray-400 text-xs">Export.pdb Table Browser</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 rounded text-xs font-medium transition-colors border border-gray-600">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="p-4">
            <div class="bg-red-900/20 border border-red-700 rounded p-3">
                <h3 class="text-red-400 font-semibold text-sm mb-1"><i class="fas fa-exclamation-circle"></i> Error</h3>
                <p class="text-red-300 text-xs"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
        <?php else: ?>
        
        <div class="border-b border-gray-700">
            <nav class="flex px-4 overflow-x-auto">
                <button onclick="showParentTab('overview')" id="overviewParentTab" class="parent-tab-button px-4 py-2.5 text-xs font-medium border-b-2 border-cyan-500 text-cyan-400 bg-gray-800/50 whitespace-nowrap">
                    <i class="fas fa-info"></i> Overview
                </button>
                <button onclick="showParentTab('library')" id="libraryParentTab" class="parent-tab-button px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap">
                    <i class="fas fa-database"></i> Library
                </button>
                <button onclick="showParentTab('playlists')" id="playlistsParentTab" class="parent-tab-button px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap">
                    <i class="fas fa-list"></i> Playlists
                </button>
                <button onclick="showParentTab('assets')" id="assetsParentTab" class="parent-tab-button px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap">
                    <i class="fas fa-folder"></i> Assets
                </button>
            </nav>
        </div>

        <div id="librarySubTabs" class="bg-gray-800/50 border-b border-gray-700 hidden">
            <div class="px-4 py-2 flex gap-1.5 flex-wrap">
                <button onclick="showSubTab('library', 'tracks')" id="tracksSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-cyan-600 text-white">
                    <i class="fas fa-music"></i> Tracks (<?= count($tracks) ?>)
                </button>
                <button onclick="showSubTab('library', 'artists')" id="artistsSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-user"></i> Artists (<?= count($artistsData) ?>)
                </button>
                <button onclick="showSubTab('library', 'albums')" id="albumsSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-compact-disc"></i> Albums (<?= count($albumsData) ?>)
                </button>
                <button onclick="showSubTab('library', 'genres')" id="genresSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-guitar"></i> Genres (<?= count($genresData) ?>)
                </button>
                <button onclick="showSubTab('library', 'keys')" id="keysSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-key"></i> Keys (<?= count($keysData) ?>)
                </button>
                <button onclick="showSubTab('library', 'labels')" id="labelsSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-tag"></i> Labels (<?= count($labelsData) ?>)
                </button>
                <button onclick="showSubTab('library', 'colors')" id="colorsSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-palette"></i> Colors (<?= count($colorsData) ?>)
                </button>
            </div>
        </div>

        <div id="playlistsSubTabs" class="bg-gray-800/50 border-b border-gray-700 hidden">
            <div class="px-4 py-2 flex gap-1.5 flex-wrap">
                <button onclick="showSubTab('playlists', 'playlists')" id="playlistsSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-cyan-600 text-white">
                    <i class="fas fa-list"></i> Playlists (<?= count($playlists) ?>)
                </button>
                <button onclick="showSubTab('playlists', 'entries')" id="entriesSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-list-ol"></i> Entries (<?= count($playlistEntries) ?>)
                </button>
                <button onclick="showSubTab('playlists', 'history')" id="historySubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-clock"></i> History
                </button>
            </div>
        </div>

        <div id="assetsSubTabs" class="bg-gray-800/50 border-b border-gray-700 hidden">
            <div class="px-4 py-2 flex gap-1.5 flex-wrap">
                <button onclick="showSubTab('assets', 'artwork')" id="artworkSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-cyan-600 text-white">
                    <i class="fas fa-image"></i> Artwork (<?= count($artworkData) ?>)
                </button>
                <button onclick="showSubTab('assets', 'columns')" id="columnsSubTab" class="sub-tab-button px-3 py-1.5 text-xs font-medium rounded bg-gray-700 text-gray-300 hover:bg-gray-600">
                    <i class="fas fa-columns"></i> Columns
                </button>
            </div>
        </div>

        <div id="overviewContent" class="tab-content p-4">
            <h2 class="text-lg font-bold text-white mb-3">Database Overview</h2>
            
            <div class="bg-gray-900 rounded border border-gray-700 p-4 mb-4">
                <h3 class="text-sm font-bold text-cyan-400 mb-3"><i class="fas fa-file-alt"></i> PDB Header</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div><span class="text-gray-500 text-xs">Page Size:</span><div class="text-white font-mono text-sm"><?= $pdbHeader['page_size'] ?? 'N/A' ?> bytes</div></div>
                    <div><span class="text-gray-500 text-xs">Total Tables:</span><div class="text-cyan-400 font-mono text-sm"><?= $pdbHeader['num_tables'] ?? 'N/A' ?></div></div>
                    <div><span class="text-gray-500 text-xs">Sequence:</span><div class="text-purple-400 font-mono text-sm"><?= $pdbHeader['sequence'] ?? 'N/A' ?></div></div>
                </div>
            </div>

            <div class="bg-gray-900 rounded border border-gray-700 p-4">
                <h3 class="text-sm font-bold text-cyan-400 mb-3"><i class="fas fa-table"></i> All Tables</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="py-2 px-2 text-left text-cyan-400">Type</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Name</th>
                                <th class="py-2 px-2 text-left text-cyan-400">First</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Last</th>
                                <th class="py-2 px-2 text-left text-cyan-400">Pages</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tablesInfo as $table): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="py-1.5 px-2 font-mono text-purple-400"><?= sprintf('0x%02X', $table['type']) ?></td>
                                <td class="py-1.5 px-2 text-white font-semibold"><?= htmlspecialchars($table['type_name']) ?></td>
                                <td class="py-1.5 px-2 font-mono text-gray-400"><?= $table['first_page'] ?></td>
                                <td class="py-1.5 px-2 font-mono text-gray-400"><?= $table['last_page'] ?></td>
                                <td class="py-1.5 px-2 text-cyan-400"><?= ($table['last_page'] - $table['first_page'] + 1) ?></td>
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
            <h2 class="text-2xl font-bold deck-title mb-4">History Playlists</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <div class="overflow-x-auto" style="max-height: 600px;">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr>
                                <th class="py-3 px-3 text-left text-cyan-400">ID</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                                <th class="py-3 px-3 text-left text-cyan-400">Entries</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($historyData)): ?>
                            <tr><td colspan="3" class="py-4 px-3 text-center text-gray-500">No history playlists found</td></tr>
                            <?php else: ?>
                                <?php foreach ($historyData as $history): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800">
                                    <td class="py-2 px-3 font-mono text-purple-400"><?= $history['id'] ?></td>
                                    <td class="py-2 px-3 text-white"><?= htmlspecialchars($history['name']) ?></td>
                                    <td class="py-2 px-3 text-cyan-400"><?= $history['entry_count'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Columns -->
        <div id="columnsContent" class="tab-content p-6 hidden">
            <h2 class="text-2xl font-bold deck-title mb-4">Columns</h2>
            <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                <?php if(empty($columnsData)): ?>
                <div class="p-6">
                    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-600 rounded p-4">
                        <h3 class="text-yellow-400 font-semibold mb-2"><i class="fas fa-info-circle"></i> No Data</h3>
                        <p class="text-yellow-300 text-sm">No columns data found in database.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="p-6">
                    <div class="bg-blue-900 bg-opacity-30 border border-blue-600 rounded p-4 mb-4">
                        <h3 class="text-blue-400 font-semibold mb-2"><i class="fas fa-info-circle"></i> About Columns Table</h3>
                        <p class="text-blue-300 text-sm">The Columns table contains metadata browsing categories used by CDJs to organize and display tracks. This includes sorting and filtering criteria available on Pioneer DJ equipment.</p>
                    </div>
                    <div class="overflow-x-auto" style="max-height: 600px;">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-gray-800">
                                <tr>
                                    <th class="py-3 px-3 text-left text-cyan-400">ID / Subtype</th>
                                    <th class="py-3 px-3 text-left text-cyan-400">Column Type</th>
                                    <th class="py-3 px-3 text-left text-cyan-400">Name</th>
                                    <th class="py-3 px-3 text-left text-cyan-400">Offset</th>
                                    <th class="py-3 px-3 text-left text-cyan-400">Raw Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columnsData as $column): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800">
                                    <td class="py-2 px-3 font-mono text-purple-400">
                                        <?= isset($column['data']['subtype']) ? $column['data']['subtype'] : ($column['data']['id'] ?? 'N/A') ?>
                                    </td>
                                    <td class="py-2 px-3 text-gray-300">
                                        <?= isset($column['data']['column_type']) ? htmlspecialchars($column['data']['column_type']) : 'Unknown' ?>
                                    </td>
                                    <td class="py-2 px-3 text-white">
                                        <?= isset($column['data']['name']) && !empty($column['data']['name']) ? htmlspecialchars($column['data']['name']) : '<span class="text-gray-500">-</span>' ?>
                                    </td>
                                    <td class="py-2 px-3 font-mono text-gray-500 text-xs">
                                        <?= $column['offset'] ?>
                                    </td>
                                    <td class="py-2 px-3">
                                        <details class="inline">
                                            <summary class="cursor-pointer text-cyan-400 hover:text-cyan-300 text-xs">
                                                <i class="fas fa-code"></i> View Hex
                                            </summary>
                                            <div class="mt-2 p-2 bg-gray-950 rounded border border-gray-700 font-mono text-xs text-gray-400 break-all">
                                                <?= htmlspecialchars($column['raw_hex']) ?>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
const defaultSubTabs = {
    'library': 'tracks',
    'playlists': 'playlists',
    'assets': 'artwork'
};

function showParentTab(parentName) {
    document.querySelectorAll('.parent-tab-button').forEach(button => {
        button.classList.remove('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById(parentName + 'ParentTab').classList.remove('border-transparent', 'text-gray-500');
    document.getElementById(parentName + 'ParentTab').classList.add('border-cyan-500', 'text-cyan-400', 'bg-gray-800');
    
    document.querySelectorAll('[id$="SubTabs"]').forEach(subTabsContainer => {
        subTabsContainer.classList.add('hidden');
    });
    
    if (parentName === 'overview') {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.getElementById('overviewContent').classList.remove('hidden');
    } else {
        const subTabsContainer = document.getElementById(parentName + 'SubTabs');
        if (subTabsContainer) {
            subTabsContainer.classList.remove('hidden');
        }
        
        const defaultSubTab = defaultSubTabs[parentName];
        if (defaultSubTab) {
            showSubTab(parentName, defaultSubTab);
        }
    }
}

function showSubTab(parentName, subTabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const parentSubTabs = document.getElementById(parentName + 'SubTabs');
    if (parentSubTabs) {
        parentSubTabs.querySelectorAll('.sub-tab-button').forEach(button => {
            button.classList.remove('bg-cyan-600', 'text-white');
            button.classList.add('bg-gray-700', 'text-gray-300');
        });
        
        const activeSubTab = document.getElementById(subTabName + 'SubTab');
        if (activeSubTab) {
            activeSubTab.classList.remove('bg-gray-700', 'text-gray-300');
            activeSubTab.classList.add('bg-cyan-600', 'text-white');
        }
    }
    
    document.getElementById(subTabName + 'Content').classList.remove('hidden');
}
</script>
</body>
</html>
