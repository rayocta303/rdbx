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
$trackOffset = 824;

echo "Scanning for 'Spaceman' string in page 2...\n\n";

for ($i = 0; $i < strlen($pageData) - 20; $i++) {
    list($str, $newOffset) = $pdbParser->extractString($pageData, $i);
    $str = trim($str);
    
    if (stripos($str, 'Spaceman') !== false) {
        $relativeOffset = $i - $trackOffset;
        echo "Found at absolute offset $i (relative to track: $relativeOffset):\n";
        echo "  '$str'\n";
        
        // Calculate which string index this might be
        if ($relativeOffset > 0x5E) {
            $stringTableOffset = $relativeOffset - 0x5E;
            if ($stringTableOffset % 2 == 0) {
                $stringIndex = $stringTableOffset / 2;
                echo "  Might be string index: $stringIndex\n";
            }
        }
        echo "\n";
    }
}

echo "\n=== RAW HEX at offset 1046 (track offset 824 + string offset 222) ===\n";
$rawOffset = 1046;
echo "Hex: " . bin2hex(substr($pageData, $rawOffset, 100)) . "\n";

echo "\n=== Trying different string extraction methods ===\n";
for ($testOffset = 1040; $testOffset < 1060; $testOffset++) {
    list($str, $newOffset) = $pdbParser->extractString($pageData, $testOffset);
    if (!empty(trim($str)) && stripos($str, 'Hard') !== false || stripos($str, 'Space') !== false) {
        echo "Offset $testOffset: '$str'\n";
    }
}
