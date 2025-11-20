<?php

require_once 'src/Parsers/PdbParser.php';

use RekordboxReader\Parsers\PdbParser;

$pdbPath = 'Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$pdbParser = new PdbParser($pdbPath);
$pdbParser->parse();

$tracksTable = $pdbParser->getTable(PdbParser::TABLE_TRACKS);
$pageData = $pdbParser->readPage(2);

echo "=== DEBUG TRACK 2 ===\n\n";

// Track 2 is at offset 824 on page 2
$offset = 824;

// Read fixed fields
$fixed = unpack(
    'Vu1/' .           // 0x00
    'Vu2/' .           // 0x04
    'Vsample_rate/' .  // 0x08
    'Vu3/' .           // 0x0C
    'Vfile_size/' .    // 0x10
    'Vu4/' .           // 0x14
    'Vu5/' .           // 0x18
    'Vu6/' .           // 0x1C
    'Vu7/' .           // 0x20
    'Vu8/' .           // 0x24
    'Vu9/' .           // 0x28
    'vu10/' .          // 0x2C
    'vu11/' .          // 0x2E
    'vbitrate/' .      // 0x30
    'vu12/' .          // 0x32
    'vu13/' .          // 0x34
    'vu14/' .          // 0x36
    'vtempo/' .        // 0x38 - BPM * 100
    'vu15/' .          // 0x3A
    'vu16/' .          // 0x3C
    'vu17/' .          // 0x3E
    'vgenre_id/' .     // 0x40
    'valbum_id/' .     // 0x42
    'vartist_id/' .    // 0x44
    'vu18/' .          // 0x46
    'Vid/' .           // 0x48 - Track ID
    'vplay_count/' .   // 0x4C
    'vyear/' .         // 0x4E
    'vsample_depth/' . // 0x50
    'vu19/' .          // 0x52
    'vu20/' .          // 0x54
    'vduration/' .     // 0x56 - Duration in seconds
    'Ccolor_id/' .     // 0x58
    'Crating/' .       // 0x59
    'vkey_id/' .       // 0x5A - Musical Key ID
    'vu22',            // 0x5C
    substr($pageData, $offset, 0x5E)
);

echo "Fixed Fields:\n";
echo "  Track ID: {$fixed['id']}\n";
echo "  Tempo (BPM*100): {$fixed['tempo']} => " . round($fixed['tempo'] / 100.0, 2) . " BPM\n";
echo "  Key ID: {$fixed['key_id']}\n";
echo "  Genre ID: {$fixed['genre_id']}\n";
echo "  Artist ID: {$fixed['artist_id']}\n";
echo "  Album ID: {$fixed['album_id']}\n";
echo "  Duration: {$fixed['duration']} seconds\n\n";

// Read string offsets
$stringOffsets = [];
$stringBase = $offset + 0x5E;

for ($i = 0; $i < 21; $i++) {
    $strOffsetData = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
    $stringOffsets[] = $strOffsetData[1];
}

echo "String Offsets:\n";
foreach ($stringOffsets as $idx => $strOffset) {
    echo "  String[$idx]: offset=$strOffset\n";
}

echo "\nString Values:\n";
foreach ($stringOffsets as $idx => $strOffset) {
    if ($strOffset > 0) {
        $absOffset = $offset + $strOffset;
        if ($absOffset < strlen($pageData)) {
            list($str, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
            
            // Show hex dump of first 32 bytes
            $hexDump = bin2hex(substr($pageData, $absOffset, min(32, strlen($pageData) - $absOffset)));
            $hexDump = chunk_split($hexDump, 2, ' ');
            
            echo "  String[$idx] @ offset $absOffset:\n";
            echo "    Hex: $hexDump\n";
            echo "    Value: '" . $str . "'\n";
        }
    }
}

// Hex dump around offset 0x5A (key_id field)
echo "\n=== Hex Dump around Key ID field (offset 0x5A) ===\n";
$keyFieldOffset = $offset + 0x5A;
$hexDump = bin2hex(substr($pageData, $keyFieldOffset - 10, 20));
$hexDump = chunk_split($hexDump, 2, ' ');
echo "Offset " . dechex($keyFieldOffset - 10) . ": $hexDump\n";

// According to reference, there might be multiple key fields
// Let's check other potential key fields
echo "\n=== Checking all 16-bit fields that might be key ===\n";
for ($checkOffset = $offset; $checkOffset < $offset + 0x90; $checkOffset += 2) {
    if ($checkOffset + 2 <= strlen($pageData)) {
        $value = unpack('v', substr($pageData, $checkOffset, 2))[1];
        if ($value == 2) { // Looking for value 2 (which would map to 2A)
            echo sprintf("Found value 2 at offset 0x%X (relative offset 0x%X)\n", 
                $checkOffset, $checkOffset - $offset);
        }
    }
}
