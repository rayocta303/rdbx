<?php

namespace RekordboxReader;

require_once __DIR__ . '/Parsers/PdbParser.php';
require_once __DIR__ . '/Parsers/TrackParser.php';
require_once __DIR__ . '/Parsers/PlaylistParser.php';
require_once __DIR__ . '/Parsers/AnlzParser.php';
require_once __DIR__ . '/Parsers/ArtistAlbumParser.php';
require_once __DIR__ . '/Parsers/GenreParser.php';
require_once __DIR__ . '/Parsers/KeyParser.php';
require_once __DIR__ . '/Parsers/ColorParser.php';
require_once __DIR__ . '/Parsers/LabelParser.php';
require_once __DIR__ . '/Parsers/HistoryParser.php';
require_once __DIR__ . '/Parsers/ColumnsParser.php';
require_once __DIR__ . '/Parsers/ArtworkParser.php';
require_once __DIR__ . '/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Parsers\PlaylistParser;
use RekordboxReader\Parsers\AnlzParser;
use RekordboxReader\Parsers\ArtistAlbumParser;
use RekordboxReader\Parsers\GenreParser;
use RekordboxReader\Parsers\KeyParser;
use RekordboxReader\Parsers\ColorParser;
use RekordboxReader\Parsers\LabelParser;
use RekordboxReader\Parsers\HistoryParser;
use RekordboxReader\Parsers\ColumnsParser;
use RekordboxReader\Parsers\ArtworkParser;
use RekordboxReader\Utils\Logger;

class RekordboxReader {
    private $exportPath;
    private $outputDir;
    private $verbose;
    private $logger;
    private $pdbPath;
    private $pdbExtPath;
    private $stats;

    public function __construct($exportPath, $outputDir = 'output', $verbose = false) {
        $this->exportPath = $exportPath;
        $this->outputDir = $outputDir;
        $this->verbose = $verbose;

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $this->logger = new Logger($this->outputDir, false);

        $this->pdbPath = $exportPath . '/PIONEER/rekordbox/export.pdb';
        $this->pdbExtPath = $exportPath . '/PIONEER/rekordbox/exportExt.pdb';

        $this->stats = [
            'total_tracks' => 0,
            'total_playlists' => 0,
            'valid_playlists' => 0,
            'corrupt_playlists' => 0,
            'anlz_files_processed' => 0,
            'processing_time' => 0
        ];
    }

    public function run() {
        $startTime = microtime(true);

        try {
            $result = $this->parseDatabase();
            
            // Parse and integrate ANLZ data into tracks
            $result['tracks'] = $this->integrateAnlzData($result['tracks']);

            $this->stats['processing_time'] = round(microtime(true) - $startTime, 2);

            $this->logger->saveCorruptPlaylistLog();

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Fatal error: " . $e->getMessage());
            throw $e;
        }
    }

    private function parseDatabase() {
        $result = [
            'tracks' => [],
            'playlists' => [],
            'metadata' => [],
            'artists' => [],
            'albums' => [],
            'genres' => [],
            'keys' => [],
            'colors' => [],
            'labels' => [],
            'history_playlists' => [],
            'history_entries' => [],
            'columns' => [],
            'artworks' => []
        ];

        if (!file_exists($this->pdbPath)) {
            throw new \Exception("export.pdb not found at {$this->pdbPath}");
        }

        $pdbParser = new PdbParser($this->pdbPath, $this->logger);
        $pdbData = $pdbParser->parse();

        $artistAlbumParser = new ArtistAlbumParser($pdbParser, $this->logger);
        $artists = $artistAlbumParser->parseArtists();
        $albums = $artistAlbumParser->parseAlbums();
        $result['artists'] = $artists;
        $result['albums'] = $albums;
        
        $genreParser = new GenreParser($pdbParser, $this->logger);
        $genres = $genreParser->parseGenres();
        $result['genres'] = $genres;
        
        $keyParser = new KeyParser($pdbParser, $this->logger);
        $keys = $keyParser->parseKeys();
        $result['keys'] = $keys;
        
        $colorParser = new ColorParser($pdbParser, $this->logger);
        $colors = $colorParser->parseColors();
        $result['colors'] = $colors;
        
        $labelParser = new LabelParser($pdbParser, $this->logger);
        $labels = $labelParser->parseLabels();
        $result['labels'] = $labels;
        
        $historyParser = new HistoryParser($pdbParser, $this->logger);
        $historyPlaylists = $historyParser->parseHistoryPlaylists();
        $historyEntries = $historyParser->parseHistoryEntries();
        $result['history_playlists'] = $historyPlaylists;
        $result['history_entries'] = $historyEntries;
        
        $columnsParser = new ColumnsParser($pdbParser, $this->logger);
        $columns = $columnsParser->parseColumns();
        $result['columns'] = $columns;
        
        $artworkParser = new ArtworkParser($pdbParser, $this->logger);
        $artworks = $artworkParser->parseArtwork();
        $result['artworks'] = $artworks;
        
        $trackParser = new TrackParser($pdbParser, $this->logger);
        $trackParser->setArtistAlbumParser($artistAlbumParser);
        $trackParser->setGenreParser($genreParser);
        $trackParser->setArtworkParser($artworkParser);
        $trackParser->setKeyParser($keyParser);
        $trackParser->setColorParser($colorParser);
        $trackParser->setLabelParser($labelParser);
        $tracks = $trackParser->parseTracks();
        $result['tracks'] = $tracks;
        $this->stats['total_tracks'] = count($tracks);

        $playlistParser = new PlaylistParser($pdbParser, $this->logger);
        $playlists = $playlistParser->parsePlaylists();
        $result['playlists'] = $playlists;

        $playlistStats = $playlistParser->getStats();
        $this->stats['total_playlists'] = $playlistStats['total_playlists'];
        $this->stats['valid_playlists'] = $playlistStats['valid_playlists'];
        $this->stats['corrupt_playlists'] = $playlistStats['corrupt_playlists'];

        $result['metadata'] = [
            'export_path' => $this->exportPath,
            'database_file' => $this->pdbPath,
            'parsed_at' => date('c'),
            'pdb_header' => $pdbData['header']
        ];

        return $result;
    }

    private function integrateAnlzData($tracks) {
        // Parse ANLZ data for each track using analyze_path from database
        if ($this->logger) {
            $this->logger->info("Starting ANLZ integration for " . count($tracks) . " tracks");
        }
        
        foreach ($tracks as &$track) {
            $track['cue_points'] = [];
            $track['waveform'] = null;
            $track['beat_grid'] = [];
            
            if (empty($track['analyze_path'])) {
                if ($this->logger && $track['id'] <= 2) {
                    $this->logger->info("Track #{$track['id']}: No analyze_path found");
                }
                continue;
            }
            
            if ($this->logger && $track['id'] <= 2) {
                $this->logger->info("Track #{$track['id']}: analyze_path = '{$track['analyze_path']}'");
            }
            
            // Normalize path: Windows backslash to POSIX forward slash
            $normalizedPath = str_replace('\\', '/', trim($track['analyze_path']));
            
            // Ensure leading slash
            if ($normalizedPath[0] !== '/') {
                $normalizedPath = '/' . $normalizedPath;
            }
            
            // Parse multiple ANLZ files and merge data
            // Different file types contain different sections:
            // .DAT = beat_grid (PQTZ), basic waveform, cues
            // .EXT = detailed waveform (PWV5), cues
            // .2EX = extended data
            // Strategy: Parse ALL files and merge to get complete data
            $filesToTry = [
                ['ext' => 'DAT', 'suffix' => '.DAT'],  // DAT first for beat grid
                ['ext' => 'EXT', 'suffix' => '.EXT'],  // EXT for better waveform
                ['ext' => '2EX', 'suffix' => '.2EX']
            ];
            
            foreach ($filesToTry as $fileInfo) {
                // Replace .DAT extension with current extension being tried
                $anlzPath = preg_replace('/\.DAT$/i', $fileInfo['suffix'], $normalizedPath);
                $fullPath = $this->exportPath . $anlzPath;
                
                if ($this->logger && !empty($track['id']) && $track['id'] <= 2) {
                    $this->logger->info("Track #{$track['id']}: Parsing {$fileInfo['ext']}: {$fullPath} - " . (file_exists($fullPath) ? 'EXISTS' : 'NOT FOUND'));
                }
                
                if (file_exists($fullPath)) {
                    try {
                        $parser = new AnlzParser($fullPath, $this->logger);
                        $anlzData = $parser->parse();

                        // Merge cue points (don't overwrite if already exists)
                        if (!empty($anlzData['cue_points']) && empty($track['cue_points'])) {
                            $track['cue_points'] = $anlzData['cue_points'];
                        }
                        
                        // Prefer EXT waveform (better quality), but accept DAT if no EXT
                        if (!empty($anlzData['waveform'])) {
                            if (empty($track['waveform']) || $fileInfo['ext'] === 'EXT') {
                                $track['waveform'] = $anlzData['waveform'];
                            }
                        }
                        
                        // Beat grid only exists in DAT files
                        if (!empty($anlzData['beat_grid']) && empty($track['beat_grid'])) {
                            $track['beat_grid'] = $anlzData['beat_grid'];
                            if ($this->logger && $track['id'] <= 2) {
                                $this->logger->info("Track #{$track['id']}: Beat grid loaded from {$fileInfo['ext']} - " . count($anlzData['beat_grid']) . " beats");
                            }
                        }

                        $this->stats['anlz_files_processed']++;

                    } catch (\Exception $e) {
                        if ($this->logger && $track['id'] <= 2) {
                            $this->logger->warning("Track #{$track['id']}: Failed to parse {$fileInfo['ext']}: " . $e->getMessage());
                        }
                        continue;
                    }
                }
            }
        }

        return $tracks;
    }

    private function printSummary() {
        $this->logger->info("");
        $this->logger->info(str_repeat("=", 60));
        $this->logger->info("PROCESSING SUMMARY");
        $this->logger->info(str_repeat("=", 60));
        $this->logger->info("Total Tracks:           " . $this->stats['total_tracks']);
        $this->logger->info("Total Playlists:        " . $this->stats['total_playlists']);
        $this->logger->info("  - Valid:              " . $this->stats['valid_playlists']);
        $this->logger->info("  - Corrupt (skipped):  " . $this->stats['corrupt_playlists']);
        $this->logger->info("ANLZ Files Processed:   " . $this->stats['anlz_files_processed']);
        $this->logger->info("Processing Time:        " . $this->stats['processing_time'] . " seconds");
        $this->logger->info(str_repeat("=", 60));
    }

    public function getStats() {
        return $this->stats;
    }
}
