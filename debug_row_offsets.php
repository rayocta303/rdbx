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

echo "Page size: $pageSize bytes\n\n";

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

echo "Num rows: $numRows\n";
echo "Page flags: 0x" . dechex($pageHeader['page_flags']) . "\n";
echo "Free size: " . $pageHeader['free_size'] . "\n";
echo "Used size: " . $pageHeader['used_size'] . "\n\n";

$heapPos = 40;
$rowIndexStart = $pageSize - 4 - (2 * $numRows);

echo "Heap position: $heapPos\n";
echo "Row index start: $rowIndexStart\n";
echo "Row index bytes (last " . (4 + 2 * $numRows) . " bytes of page):\n";
echo bin2hex(substr($pageData, $rowIndexStart)) . "\n\n";

for ($rowIdx = 0; $rowIdx < $numRows; $rowIdx++) {
    $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
    echo "Row $rowIdx offset position: $rowOffsetPos\n";
    
    $rowOffsetBytes = substr($pageData, $rowOffsetPos, 2);
    echo "  Raw bytes: " . bin2hex($rowOffsetBytes) . "\n";
    
    $rowOffsetData = unpack('v', $rowOffsetBytes);
    $rowOffset = $rowOffsetData[1];
    echo "  Row offset value: $rowOffset (0x" . dechex($rowOffset) . ")\n";
    
    $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
    echo "  Actual row offset: $actualRowOffset\n";
    echo "  (calculation: ($rowOffset & 0x1FFF) + $heapPos)\n\n";
}
