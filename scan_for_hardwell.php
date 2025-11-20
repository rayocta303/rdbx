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

echo "Scanning for BPM 128 (tempo 0x3200 = 12800)...\n";
echo "Scanning for string 'Hardwell'...\n";
echo "Scanning for string 'Spaceman'...\n\n";

// Scan for BPM 128 (12800 = 0x3200 in little endian = 0032)
$bpm128Pattern = pack('v', 12800);
$pos = strpos($pageData, $bpm128Pattern);
if ($pos !== false) {
    echo "Found BPM 128 at byte offset: $pos (0x" . dechex($pos) . ")\n";
    echo "Potential track row offset: " . ($pos - 0x38) . "\n";
    
    // Try to read track data from this offset
    $trackOffset = $pos - 0x38 - 40; // minus BPM offset, minus heap
    echo "Row offset value would be: $trackOffset\n\n";
}

// Scan for "Hardwell"
for ($i = 40; $i < $pageSize - 20; $i++) {
    list($str, $newOffset) = $pdbParser->extractString($pageData, $i);
    $str = trim($str);
    
    if (stripos($str, 'Hardwell') !== false || stripos($str, 'Spaceman') !== false) {
        echo "Found string '$str' at offset $i\n";
        
        // Try to find which row this belongs to
        // String table starts at row_offset + 0x5E
        echo "This suggests row starts at: " . ($i - 0x5E - 100) . " to " . ($i - 0x5E) . "\n\n";
    }
}
