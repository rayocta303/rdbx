<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$logger = new Logger(__DIR__ . '/output', false);

$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

$pageData = $pdbParser->readPage(2);
$offset = 824; // Track 2 offset

echo "=== PARSING TRACK 2 AT OFFSET $offset ===\n\n";

// Parse fixed data
$fixed = unpack(
    'Vu1/Vu2/Vsample_rate/Vu3/Vfile_size/Vu4/Vu5/Vu6/Vu7/Vu8/Vu9/' .
    'vu10/vu11/vbitrate/vu12/vu13/vu14/vtempo/vu15/vu16/vu17/' .
    'vgenre_id/valbum_id/vartist_id/vu18/vid/vplay_count/vyear/vsample_depth/vu19/vu20/vduration',
    substr($pageData, $offset, 0x56)
);

echo "Track ID (at +0x48): " . $fixed['id'] . "\n";
echo "BPM (at +0x38): " . ($fixed['tempo'] / 100.0) . "\n";
echo "Duration (at +0x54): " . $fixed['duration'] . " seconds\n";
echo "Artist ID: " . $fixed['artist_id'] . "\n";
echo "Genre ID: " . $fixed['genre_id'] . "\n\n";

// Parse string table
$stringBase = $offset + 0x5E;

echo "String offsets:\n";
for ($i = 0; $i < 21; $i++) {
    $strOffsetBytes = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
    $strOffset = $strOffsetBytes[1];
    
    echo "  String[$i] offset: $strOffset";
    
    if ($strOffset > 0) {
        $absOffset = $offset + $strOffset;
        if ($absOffset < strlen($pageData)) {
            list($str, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
            
            $nullPos = strpos($str, "\x00");
            if ($nullPos !== false) {
                $str = substr($str, 0, $nullPos);
            }
            
            if (strpos($str, ';') !== false) {
                $parts = explode(';', $str);
                $str = $parts[0];
            }
            
            $str = trim($str);
            if (strlen($str) > 60) $str = substr($str, 0, 60) . '...';
            
            echo " => '$str'";
        }
    }
    echo "\n";
}
