<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$logger = new Logger(__DIR__ . '/output', false);
$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

$genresTable = $pdbParser->getTable(1);

echo "=== GENRE TABLE ===\n";
echo "First Page: {$genresTable['first_page']}\n";
echo "Last Page: {$genresTable['last_page']}\n\n";

for ($pageIdx = $genresTable['first_page']; $pageIdx <= $genresTable['last_page']; $pageIdx++) {
    echo "=== PAGE {$pageIdx} ===\n";
    $pageData = $pdbParser->readPage($pageIdx);
    
    if (!$pageData || strlen($pageData) < 48) {
        echo "Page too small\n\n";
        continue;
    }

    $pageHeader = unpack(
        'Vgap/Vpage_idx/Vtype/Vnext/Vu1/Vu2/' .
        'Cnum_rows/Cu1/Cu2/Cflags/vfree_size/vused_size/vu3/vnumrl',
        substr($pageData, 0, 28)
    );

    echo "Page Header:\n";
    echo "  Num Rows: {$pageHeader['num_rows']}\n";
    echo "  Flags: " . dechex($pageHeader['flags']) . "\n";
    echo "  Is Data Page: " . (($pageHeader['flags'] & 0x40) == 0 ? 'yes' : 'no') . "\n";
    echo "  Free Size: {$pageHeader['free_size']}\n";
    echo "  Used Size: {$pageHeader['used_size']}\n\n";

    $isDataPage = ($pageHeader['flags'] & 0x40) == 0;
    
    if (!$isDataPage) {
        echo "Not a data page, skipping\n\n";
        continue;
    }

    $numRows = $pageHeader['num_rows'];
    if ($numRows == 0) {
        echo "No rows\n\n";
        continue;
    }

    echo "Attempting to parse {$numRows} rows...\n";
    
    // Try to find strings in the heap
    $heapStart = 40;
    $heapEnd = strlen($pageData) - 100;
    
    echo "\nScanning for genre strings in heap...\n";
    for ($offset = $heapStart; $offset < $heapEnd; $offset++) {
        $flags = ord($pageData[$offset]);
        
        // Check for string marker
        if (($flags & 0x40) == 0) {
            $len = $flags & 0x7F;
            if ($len >= 3 && $len < 100 && ($offset + $len + 1) <= strlen($pageData)) {
                $str = substr($pageData, $offset + 1, $len);
                
                // Check if it looks like a genre name
                if (preg_match('/^[A-Za-z0-9\s\-]+$/', $str) && strlen(trim($str)) >= 3) {
                    echo "  Found string at offset {$offset}: \"" . trim($str) . "\"\n";
                }
            }
        }
    }
    
    echo "\n";
}
