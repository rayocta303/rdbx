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

$tracksTable = $pdbParser->getTable(PdbParser::TABLE_TRACKS);
$keysTable = $pdbParser->getTable(PdbParser::TABLE_KEYS);

echo "=== KEYS TABLE ===\n";
if ($keysTable) {
    for ($pageIdx = $keysTable['first_page']; $pageIdx <= $keysTable['last_page']; $pageIdx++) {
        $pageData = $pdbParser->readPage($pageIdx);
        if (!$pageData || strlen($pageData) < 48) continue;
        
        echo "Keys in page $pageIdx:\n";
        echo "Hex dump of first 100 bytes:\n";
        echo bin2hex(substr($pageData, 0, 100)) . "\n\n";
    }
}

echo "\n=== TRACK DETAIL FROM PAGE 2 ===\n";
$pageData = $pdbParser->readPage(2);

if ($pageData && strlen($pageData) >= 48) {
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
    
    echo "Num rows in page: $numRows\n";
    
    $heapPos = 40;
    $pageSize = strlen($pageData);
    $rowIndexStart = $pageSize - 4 - (2 * $numRows);
    
    for ($rowIdx = 0; $rowIdx < min(2, $numRows); $rowIdx++) {
        $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
        $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
        $rowOffset = $rowOffsetData[1];
        $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
        
        echo "\n--- Row $rowIdx at offset $actualRowOffset ---\n";
        echo "Hex dump of fixed data (first 94 bytes):\n";
        echo bin2hex(substr($pageData, $actualRowOffset, 94)) . "\n\n";
        
        $fixed = unpack(
            'Vu1/Vu2/Vsample_rate/Vu3/Vfile_size/Vu4/Vu5/Vu6/Vu7/Vu8/Vu9/' .
            'vu10/vu11/vbitrate/vu12/vu13/vu14/vtempo/vu15/vu16/vu17/' .
            'vgenre_id/valbum_id/vartist_id/vid/vplay_count/vu18/vyear/vsample_depth/vu19/vduration',
            substr($pageData, $actualRowOffset, 0x54)
        );
        
        echo "Fixed data:\n";
        echo "  Track ID: " . $fixed['id'] . "\n";
        echo "  BPM (tempo/100): " . ($fixed['tempo'] / 100.0) . "\n";
        echo "  Duration: " . $fixed['duration'] . " seconds\n";
        echo "  Artist ID: " . $fixed['artist_id'] . "\n";
        echo "  Genre ID: " . $fixed['genre_id'] . "\n";
        echo "  Album ID: " . $fixed['album_id'] . "\n";
    }
}
