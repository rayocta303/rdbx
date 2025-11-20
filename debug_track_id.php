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
$pageSize = strlen($pageData);

$pageHeader = unpack(
    'Vgap/Vpage_index/Vtype/Vnext_page/Vu1/Vu2/' .
    'Cnum_rows_small/Cu3/Cu4/Cpage_flags/' .
    'vfree_size/vused_size/vu5/vnum_rows_large',
    substr($pageData, 0, 36)
);

$numRows = $pageHeader['num_rows_large'];
if ($numRows == 0 || $numRows == 0x1fff) {
    $numRows = $pageHeader['num_rows_small'];
}

$heapPos = 40;
$rowIndexStart = $pageSize - 4 - (2 * $numRows);

for ($rowIdx = 0; $rowIdx < $numRows; $rowIdx++) {
    $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
    $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
    $rowOffset = $rowOffsetData[1];
    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
    
    echo "\n=== TRACK $rowIdx at offset $actualRowOffset ===\n";
    
    // Read track ID at offset 0x46 (2 bytes)
    $trackIdOffset = $actualRowOffset + 0x46;
    $trackIdBytes = unpack('v', substr($pageData, $trackIdOffset, 2));
    $trackId = $trackIdBytes[1];
    
    echo "Track ID (at 0x46): $trackId\n";
    
    // Read BPM at offset 0x38 (2 bytes)
    $bpmOffset = $actualRowOffset + 0x38;
    $bpmBytes = unpack('v', substr($pageData, $bpmOffset, 2));
    $bpm = $bpmBytes[1] / 100.0;
    
    echo "BPM (at 0x38): $bpm\n";
    
    // Read Genre ID at offset 0x40 (2 bytes)
    $genreIdOffset = $actualRowOffset + 0x40;
    $genreIdBytes = unpack('v', substr($pageData, $genreIdOffset, 2));
    $genreId = $genreIdBytes[1];
    
    echo "Genre ID (at 0x40): $genreId\n";
    
    // Read Artist ID at offset 0x44 (2 bytes)
    $artistIdOffset = $actualRowOffset + 0x44;
    $artistIdBytes = unpack('v', substr($pageData, $artistIdOffset, 2));
    $artistId = $artistIdBytes[1];
    
    echo "Artist ID (at 0x44): $artistId\n";
    
    // Read string offset for title (string[17])
    $stringBase = $actualRowOffset + 0x5E;
    $titleOffsetPos = $stringBase + (17 * 2);
    $titleOffsetBytes = unpack('v', substr($pageData, $titleOffsetPos, 2));
    $titleOffset = $titleOffsetBytes[1];
    
    if ($titleOffset > 0) {
        $absOffset = $actualRowOffset + $titleOffset;
        list($title, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
        
        if (strpos($title, ';') !== false) {
            $parts = explode(';', $title);
            $title = $parts[0];
        }
        
        echo "Title: " . trim($title) . "\n";
    }
}
