<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$logger = new Logger(__DIR__ . '/output', false);
$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

$header = $pdbParser->getHeader();
echo "PDB File Header:\n";
echo "  Page Size: {$header['page_size']} bytes\n";
echo "  Num Tables: {$header['num_tables']}\n\n";

$tables = $pdbParser->getTables();
foreach ($tables as $tableType => $table) {
    echo "Table {$tableType} ({$table['type_name']}):\n";
    echo "  First Page: {$table['first_page']}\n";
    echo "  Last Page: {$table['last_page']}\n";
    
    // Check if pages exist
    for ($pageIdx = $table['first_page']; $pageIdx <= $table['last_page']; $pageIdx++) {
        $pageData = $pdbParser->readPage($pageIdx);
        echo "  Page {$pageIdx}: " . (strlen($pageData) ?? '0') . " bytes\n";
    }
    
    echo "\n";
}
