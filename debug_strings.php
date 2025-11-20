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

echo "=== DEBUG TRACK STRINGS ===\n\n";

$firstPage = $tracksTable['first_page'];
$lastPage = $tracksTable['last_page'];

$trackCount = 0;
for ($pageIdx = $firstPage; $pageIdx <= $lastPage && $trackCount < 3; $pageIdx++) {
    $pageData = $pdbParser->readPage($pageIdx);
    if (!$pageData || strlen($pageData) < 48) continue;
    
    $pageHeader = unpack(
        'Vgap/Vpage_index/Vtype/Vnext_page/Vu1/Vu2/' .
        'Cnum_rows_small/Cu3/Cu4/Cpage_flags/' .
        'vfree_size/vused_size/vu5/vnum_rows_large',
        substr($pageData, 0, 36)
    );
    
    $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
    if (!$isDataPage) continue;
    
    $numRows = $pageHeader['num_rows_large'];
    if ($numRows == 0 || $numRows == 0x1fff) {
        $numRows = $pageHeader['num_rows_small'];
    }
    
    $heapPos = 40;
    $pageSize = strlen($pageData);
    $rowIndexStart = $pageSize - 4 - (2 * $numRows);
    
    for ($rowIdx = 0; $rowIdx < $numRows && $trackCount < 3; $rowIdx++) {
        $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
        if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) continue;
        
        $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
        $rowOffset = $rowOffsetData[1];
        $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
        
        if ($actualRowOffset >= $pageSize || $actualRowOffset + 200 > $pageSize) continue;
        
        $trackCount++;
        echo "\n--- TRACK $trackCount (offset $actualRowOffset) ---\n";
        
        $stringBase = $actualRowOffset + 0x5E;
        
        echo "String offsets:\n";
        for ($i = 0; $i < 21; $i++) {
            $strOffsetData = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
            $strOffset = $strOffsetData[1];
            echo "  [$i] offset: $strOffset";
            
            if ($strOffset > 0) {
                $absOffset = $actualRowOffset + $strOffset;
                if ($absOffset < strlen($pageData)) {
                    list($str, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
                    $str = trim($str);
                    if (strlen($str) > 50) $str = substr($str, 0, 50) . '...';
                    echo " => '$str'";
                }
            }
            echo "\n";
        }
    }
}
