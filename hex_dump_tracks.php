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

echo "=== ROW INDEX (last 8 bytes of page) ===\n";
$rowIndexBytes = substr($pageData, -8);
echo "Hex: " . bin2hex($rowIndexBytes) . "\n";
echo "Interpretation 1 (2 shorts): ";
$shorts = unpack('v*', $rowIndexBytes);
print_r($shorts);

echo "\n=== SEARCHING FOR DURATION 183 seconds (0xB7 = 183) ===\n";
$duration1 = pack('v', 183);
$pos = strpos($pageData, $duration1);
if ($pos !== false) {
    echo "Found 183 at offset: $pos (0x" . dechex($pos) . ")\n";
    echo "Relative to track 1 start (432): " . ($pos - 432) . " (0x" . dechex($pos - 432) . ")\n";
}

echo "\n=== SEARCHING FOR DURATION 100 seconds (0x64 = 100) ===\n";
$duration2 = pack('v', 100);
$positions = [];
$offset = 0;
while (($pos = strpos($pageData, $duration2, $offset)) !== false) {
    $positions[] = $pos;
    $offset = $pos + 1;
}
echo "Found 100 at offsets: " . implode(', ', $positions) . "\n";

echo "\n=== SEARCHING FOR TRACK ID 1 and 2 ===\n";
echo "Possible track ID patterns near expected offsets:\n";

// Check berbagai offset untuk track IDs
$trackOffsets = [432, 824];
foreach ($trackOffsets as $trackNum => $trackOffset) {
    echo "\nTrack " . ($trackNum + 1) . " at offset $trackOffset:\n";
    
    // Check first 100 bytes for ID patterns
    for ($i = 0; $i < 100; $i += 2) {
        $value = unpack('v', substr($pageData, $trackOffset + $i, 2));
        if ($value[1] == ($trackNum + 1)) {
            echo "  Found value " . ($trackNum + 1) . " at offset +0x" . dechex($i) . "\n";
        }
    }
}

// Dump first 150 bytes of each track
foreach ($trackOffsets as $trackNum => $trackOffset) {
    echo "\n=== HEX DUMP TRACK " . ($trackNum + 1) . " (first 150 bytes) ===\n";
    echo "Offset $trackOffset:\n";
    $hex = bin2hex(substr($pageData, $trackOffset, 150));
    
    for ($i = 0; $i < strlen($hex); $i += 32) {
        $offset_label = str_pad(dechex($i / 2), 4, '0', STR_PAD_LEFT);
        echo $offset_label . ': ' . substr($hex, $i, 32) . "\n";
    }
}
