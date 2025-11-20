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

$trackOffsets = [432, 824];
$expectedKeys = [1, 2]; // Track 1 = key 1 (9A), Track 2 = key 2 (2A)

foreach ($trackOffsets as $idx => $offset) {
    echo "=== TRACK " . ($idx + 1) . " ===\n";
    
    // Track ID at 0x48
    $trackId = unpack('v', substr($pageData, $offset + 0x48, 2))[1];
    echo "Track ID (at +0x48): $trackId\n";
    
    // Duration at 0x54
    $duration = unpack('v', substr($pageData, $offset + 0x54, 2))[1];
    echo "Duration (at +0x54): $duration seconds (" . gmdate("i:s", $duration) . ")\n";
    
    // BPM at 0x38
    $bpm = unpack('v', substr($pageData, $offset + 0x38, 2))[1] / 100.0;
    echo "BPM (at +0x38): $bpm\n";
    
    // Check potential key_id locations
    echo "Potential key_id fields:\n";
    $keyFieldOffsets = [0x5A, 0x5C, 0x56, 0x58];
    foreach ($keyFieldOffsets as $keyOffset) {
        $value = unpack('v', substr($pageData, $offset + $keyOffset, 2))[1];
        $match = ($value == $expectedKeys[$idx]) ? " ← MATCH!" : "";
        echo "  +0x" . dechex($keyOffset) . ": $value$match\n";
    }
    
    echo "\n";
}
