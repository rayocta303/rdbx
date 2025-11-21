<?php

namespace RekordboxReader;

require_once __DIR__ . '/Parsers/PdbParser.php';
require_once __DIR__ . '/Parsers/TrackParser.php';
require_once __DIR__ . '/Parsers/PlaylistParser.php';
require_once __DIR__ . '/Parsers/AnlzParser.php';
require_once __DIR__ . '/Parsers/ArtistAlbumParser.php';
require_once __DIR__ . '/Parsers/GenreParser.php';
require_once __DIR__ . '/Parsers/KeyParser.php';
require_once __DIR__ . '/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Parsers\PlaylistParser;
use RekordboxReader\Parsers\AnlzParser;
use RekordboxReader\Parsers\ArtistAlbumParser;
use RekordboxReader\Parsers\GenreParser;
use RekordboxReader\Parsers\KeyParser;
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
            'metadata' => []
        ];

        if (!file_exists($this->pdbPath)) {
            throw new \Exception("export.pdb not found at {$this->pdbPath}");
        }

        $pdbParser = new PdbParser($this->pdbPath, $this->logger);
        $pdbData = $pdbParser->parse();

        $artistAlbumParser = new ArtistAlbumParser($pdbParser, $this->logger);
        $artists = $artistAlbumParser->parseArtists();
        $albums = $artistAlbumParser->parseAlbums();
        
        $genreParser = new GenreParser($pdbParser, $this->logger);
        $genres = $genreParser->parseGenres();
        
        $keyParser = new KeyParser($pdbParser, $this->logger);
        $keys = $keyParser->parseKeys();
        
        $trackParser = new TrackParser($pdbParser, $this->logger);
        $trackParser->setArtistAlbumParser($artistAlbumParser);
        $trackParser->setGenreParser($genreParser);
        $trackParser->setKeyParser($keyParser);
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
            
            // Try to find and parse ANLZ files (.EXT, .DAT, .2EX)
            // Priority: EXT files have the most complete data
            $filesToTry = [
                ['ext' => 'EXT', 'suffix' => '.EXT'],
                ['ext' => 'DAT', 'suffix' => '.DAT'],
                ['ext' => '2EX', 'suffix' => '.2EX']
            ];
            
            $hasData = false;
            foreach ($filesToTry as $fileInfo) {
                // Replace .DAT extension with current extension being tried
                $anlzPath = preg_replace('/\.DAT$/i', $fileInfo['suffix'], $normalizedPath);
                $fullPath = $this->exportPath . $anlzPath;
                
                if ($this->logger && !empty($track['id']) && $track['id'] <= 2) {
                    $this->logger->info("Track #{$track['id']}: Trying ANLZ: {$fullPath} - " . (file_exists($fullPath) ? 'EXISTS' : 'NOT FOUND'));
                }
                
                if (file_exists($fullPath)) {
                    try {
                        $parser = new AnlzParser($fullPath, $this->logger);
                        $anlzData = $parser->parse();

                        if (!empty($anlzData['cue_points'])) {
                            $track['cue_points'] = $anlzData['cue_points'];
                            $hasData = true;
                        }
                        
                        if (!empty($anlzData['waveform'])) {
                            $track['waveform'] = $anlzData['waveform'];
                            $hasData = true;
                        }
                        
                        if (!empty($anlzData['beat_grid'])) {
                            $track['beat_grid'] = $anlzData['beat_grid'];
                            $hasData = true;
                        }

                        // Only break if we got actual data
                        if ($hasData) {
                            $this->stats['anlz_files_processed']++;
                            break;
                        }

                    } catch (\Exception $e) {
                        // Try next file type
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
