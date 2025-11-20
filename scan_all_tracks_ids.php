<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Parsers/TrackParser.php';
require_once __DIR__ . '/src/Parsers/KeyParser.php';
require_once __DIR__ . '/src/Parsers/GenreParser.php';
require_once __DIR__ . '/src/Parsers/ArtistAlbumParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Parsers\KeyParser;
use RekordboxReader\Parsers\GenreParser;
use RekordboxReader\Parsers\ArtistAlbumParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$logger = new Logger(__DIR__ . '/output', true);

$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

// Parse genres
$genreParser = new GenreParser($pdbParser, $logger);
$genres = $genreParser->parseGenres();

echo "\n=== GENRES ===\n";
print_r($genres);

// Parse artists
$artistParser = new ArtistAlbumParser($pdbParser, $logger);
$artists = $artistParser->parseArtists();

echo "\n=== ARTISTS ===\n";
print_r($artists);

// Parse keys
$keyParser = new KeyParser($pdbParser, $logger);
$keys = $keyParser->parseKeys();

echo "\n=== KEYS ===\n";
print_r($keys);

// Parse tracks with all parsers
$trackParser = new TrackParser($pdbParser, $logger);
$trackParser->setGenreParser($genreParser);
$trackParser->setArtistAlbumParser($artistParser);
$trackParser->setKeyParser($keyParser);

$tracks = $trackParser->parseTracks();

echo "\n=== TRACKS ===\n";
foreach ($tracks as $track) {
    echo "\nTrack ID: {$track['id']}\n";
    echo "  Title: {$track['title']}\n";
    echo "  Artist: {$track['artist']}\n";
    echo "  Genre: {$track['genre']}\n";
    echo "  BPM: {$track['bpm']}\n";
    echo "  Key: {$track['key']}\n";
    echo "  Analyze Path: {$track['analyze_path']}\n";
}
