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

$trackOffsets = [432, 824]; // dari debugging sebelumnya

foreach ($trackOffsets as $idx => $offset) {
    echo "\n=== TRACK " . ($idx + 1) . " at offset $offset ===\n";
    
    // Parse fixed data
    $fixed = unpack(
        'Vu1/Vu2/Vsample_rate/Vu3/Vfile_size/Vu4/Vu5/Vu6/Vu7/Vu8/Vu9/' .
        'vu10/vu11/vbitrate/vu12/vu13/vu14/vtempo/vu15/vu16/vu17/' .
        'vgenre_id/valbum_id/vartist_id/vid/vplay_count/vu18/vyear/vsample_depth/vu19/vduration',
        substr($pageData, $offset, 0x54)
    );
    
    echo "Track ID: " . $fixed['id'] . "\n";
    echo "BPM: " . ($fixed['tempo'] / 100.0) . "\n";
    echo "Duration: " . $fixed['duration'] . " seconds (" . gmdate("i:s", $fixed['duration']) . ")\n";
    echo "Artist ID: " . $fixed['artist_id'] . "\n";
    echo "Genre ID: " . $fixed['genre_id'] . "\n";
    
    // Parse strings
    $stringBase = $offset + 0x5E;
    $titleOffsetBytes = unpack('v', substr($pageData, $stringBase + (17 * 2), 2));
    $titleOffset = $titleOffsetBytes[1];
    
    if ($titleOffset > 0) {
        $absOffset = $offset + $titleOffset;
        list($title, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
        if (strpos($title, ';') !== false) {
            $parts = explode(';', $title);
            $title = $parts[0];
        }
        echo "Title: " . trim($title) . "\n";
    }
    
    // Check all string offsets to find key_id
    echo "\nLooking for key_id in strings:\n";
    for ($i = 0; $i < 10; $i++) {
        $strOffsetBytes = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
        $strOffset = $strOffsetBytes[1];
        
        if ($strOffset > 0) {
            $absOffset = $offset + $strOffset;
            list($str, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
            $str = trim($str);
            
            if (is_numeric($str) && intval($str) > 0 && intval($str) < 100) {
                echo "  String[$i]: '$str' (numeric, could be key_id)\n";
            }
        }
    }
    
    // Check fixed fields for potential key_id
    echo "\nChecking fixed fields for key patterns:\n";
    for ($fieldOffset = 0x54; $fieldOffset < 0x5E; $fieldOffset += 2) {
        $value = unpack('v', substr($pageData, $offset + $fieldOffset, 2));
        if ($value[1] > 0 && $value[1] < 100) {
            echo "  Offset 0x" . dechex($fieldOffset) . ": " . $value[1] . "\n";
        }
    }
}
