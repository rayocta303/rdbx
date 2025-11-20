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

echo "Tracks table info:\n";
echo "  First page: " . $tracksTable['first_page'] . "\n";
echo "  Last page: " . $tracksTable['last_page'] . "\n\n";

for ($pageIdx = $tracksTable['first_page']; $pageIdx <= $tracksTable['last_page']; $pageIdx++) {
    $pageData = $pdbParser->readPage($pageIdx);
    if (!$pageData || strlen($pageData) < 48) {
        echo "Page $pageIdx: too small\n";
        continue;
    }
    
    $pageHeader = unpack(
        'Vgap/Vpage_index/Vtype/Vnext_page/Vu1/Vu2/' .
        'Cnum_rows_small/Cu3/Cu4/Cpage_flags/' .
        'vfree_size/vused_size/vu5/vnum_rows_large',
        substr($pageData, 0, 36)
    );
    
    $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
    $numRows = $pageHeader['num_rows_large'];
    if ($numRows == 0 || $numRows == 0x1fff) {
        $numRows = $pageHeader['num_rows_small'];
    }
    
    echo "Page $pageIdx: ";
    echo "is_data=" . ($isDataPage ? 'yes' : 'no') . ", ";
    echo "num_rows=$numRows, ";
    echo "page_flags=0x" . dechex($pageHeader['page_flags']) . "\n";
}
