<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$logger = new Logger(__DIR__ . '/output', false);
$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

$tracksTable = $pdbParser->getTable(0);
$firstPage = $tracksTable['first_page'];
$lastPage = $tracksTable['last_page'];

for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
    $pageData = $pdbParser->readPage($pageIdx);
    if (!$pageData || strlen($pageData) < 48) continue;

    $pageHeader = unpack(
        'Vgap/Vpage_index/Vtype/Vnext_page/Vunknown1/Vunknown2/' .
        'Cnum_rows_small/Cu3/Cu4/Cpage_flags/vfree_size/vused_size/vu5/vnum_rows_large',
        substr($pageData, 0, 36)
    );

    $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
    if (!$isDataPage) continue;

    $numRows = $pageHeader['num_rows_large'];
    if ($numRows == 0 || $numRows == 0x1fff) {
        $numRows = $pageHeader['num_rows_small'];
    }
    if ($numRows == 0) continue;

    $heapPos = 40;
    $pageSize = strlen($pageData);
    $rowIndexStart = $pageSize - 4 - (2 * $numRows);
    
    for ($rowIdx = 0; $rowIdx < $numRows; $rowIdx++) {
        $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
        if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) continue;
        
        $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
        $rowOffset = $rowOffsetData[1];
        $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
        
        if ($actualRowOffset >= $pageSize || $actualRowOffset + 200 > $pageSize) continue;

        if ($actualRowOffset + 0x94 > strlen($pageData)) continue;
        
        $fixed = unpack(
            'Vu1/Vu2/Vsample_rate/Vu3/Vfile_size/Vu4/Vu5/Vu6/Vu7/Vu8/Vu9/' .
            'vu10/vu11/vbitrate/vu12/vu13/vu14/vtempo/vu15/vu16/vu17/' .
            'vgenre_id/valbum_id/vartist_id/vu18/vid',
            substr($pageData, $actualRowOffset, 0x50)
        );

        if ($fixed['id'] == 2) {
            echo "Found Track ID 2 at page {$pageIdx}, row {$rowIdx}, offset {$actualRowOffset}\n\n";
            
            // Parse all strings
            $stringOffsets = [];
            $stringBase = $actualRowOffset + 0x5E;
            
            for ($i = 0; $i < 21; $i++) {
                $strOffsetData = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
                $stringOffsets[] = $strOffsetData[1];
            }

            echo "String Offsets: " . implode(', ', $stringOffsets) . "\n\n";

            foreach ($stringOffsets as $idx => $strOffset) {
                if ($strOffset > 0) {
                    $absOffset = $actualRowOffset + $strOffset;
                    if ($absOffset < strlen($pageData)) {
                        list($str, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
                        
                        echo "String[$idx] (offset=$strOffset, abs=$absOffset): ";
                        echo "\"" . $str . "\"\n";
                        
                        // Show hex
                        $hexDump = bin2hex(substr($pageData, $absOffset, min(100, strlen($pageData) - $absOffset)));
                        echo "  Hex: " . substr($hexDump, 0, 100) . "\n\n";
                    }
                }
            }
            
            break 2;
        }
    }
}
