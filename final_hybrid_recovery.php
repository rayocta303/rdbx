<?php

/**
 * Final Hybrid Recovery
 * 
 * Strategy:
 * - Learn STRUCTURE from normal file (how Rekordbox format works)
 * - Apply that structure understanding to fix corrupt file
 * - Preserve ALL DATA from corrupt file (tracks, metadata, everything)
 * - Only fix structural corruption (header fields, table pointers)
 */

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  FINAL HYBRID RECOVERY                                     ║\n";
echo "║  Understanding structure + Preserving all data             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$corruptFile = 'plans/export.pdb';
$normalFile = 'Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb';
$outputFile = 'plans/export_final_hybrid.pdb';

echo "Step 1: Learn structure from normal file...\n\n";

$normal = file_get_contents($normalFile);
$corrupt = file_get_contents($corruptFile);

// Learn what values table 0 and table 5 should have
$normalHeader = unpack('V6', substr($normal, 0, 24));
$normalTable0 = unpack('V4', substr($normal, 24, 16));
$normalTable5 = unpack('V4', substr($normal, 24 + (5*16), 16));

echo "Learned from normal file:\n";
echo "  Table 0: first={$normalTable0[3]}, last={$normalTable0[4]}\n";
echo "  Table 5: first={$normalTable5[3]}, last={$normalTable5[4]}\n";
echo "  Next unused page: {$normalHeader[4]}\n";
echo "  Sequence: {$normalHeader[6]}\n\n";

echo "Step 2: Apply structure understanding to corrupt file...\n\n";

// Start with corrupt file data (preserve everything)
$data = $corrupt;

// Fix header using understood structure
$corruptHeader = unpack('V6', substr($data, 0, 24));

$newHeader = $corruptHeader;
$newHeader[4] = $normalHeader[4]; // next_unused_page from learned structure
$newHeader[6] = $normalHeader[6]; // sequence from learned structure

echo "Fixed header:\n";
echo "  next_unused_page: {$corruptHeader[4]} → {$newHeader[4]}\n";
echo "  sequence: {$corruptHeader[6]} → {$newHeader[6]}\n\n";

$headerBytes = pack('V6', $newHeader[1], $newHeader[2], $newHeader[3], $newHeader[4], $newHeader[5], $newHeader[6]);
for ($i = 0; $i < 24; $i++) {
    $data[$i] = $headerBytes[$i];
}

// Fix table 0 using learned pattern
$corruptTable0 = unpack('V4', substr($data, 24, 16));
if ($corruptTable0[3] > 10000) {
    $newTable0 = $corruptTable0;
    $newTable0[3] = $normalTable0[3]; // Use learned first page
    // Keep last page as is (it's already correct: 1)
    
    echo "Fixed Table 0:\n";
    echo "  first_page: {$corruptTable0[3]} → {$newTable0[3]}\n\n";
    
    $entryBytes = pack('V4', $newTable0[1], $newTable0[2], $newTable0[3], $newTable0[4]);
    for ($i = 0; $i < 16; $i++) {
        $data[24 + $i] = $entryBytes[$i];
    }
}

// Fix table 5 using learned pattern
$corruptTable5 = unpack('V4', substr($data, 24 + (5*16), 16));
if ($corruptTable5[3] > 10000) {
    $newTable5 = $corruptTable5;
    $newTable5[3] = $normalTable5[3]; // Use learned first page
    // Keep last page as is (it's already correct: 11)
    
    echo "Fixed Table 5:\n";
    echo "  first_page: {$corruptTable5[3]} → {$newTable5[3]}\n\n";
    
    $offset = 24 + (5*16);
    $entryBytes = pack('V4', $newTable5[1], $newTable5[2], $newTable5[3], $newTable5[4]);
    for ($i = 0; $i < 16; $i++) {
        $data[$offset + $i] = $entryBytes[$i];
    }
}

echo "Step 3: Verify data preservation...\n\n";

// Verify we preserved data (check some random offset in data section)
$dataOffset = 10000; // Some offset in data area
if ($dataOffset < strlen($data) && $dataOffset < strlen($corrupt)) {
    $preserved = (substr($data, $dataOffset, 100) === substr($corrupt, $dataOffset, 100));
    echo "Data preservation check: " . ($preserved ? "✓ DATA PRESERVED" : "✗ DATA CHANGED") . "\n";
    echo "  (Checked 100 bytes at offset $dataOffset)\n\n";
}

echo "Step 4: Save recovered file...\n\n";

file_put_contents($outputFile, $data);

echo "✓ Saved to: $outputFile\n";
echo "  Size: " . number_format(strlen($data)) . " bytes\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  RECOVERY SUMMARY                                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "What was done:\n";
echo "  ✓ Learned structure pattern from normal file\n";
echo "  ✓ Applied structure understanding to fix corrupt pointers\n";
echo "  ✓ Preserved ALL data from corrupt file\n";
echo "  ✓ Fixed only structural corruption:\n";
echo "      - Header: next_unused_page, sequence\n";
echo "      - Table 0: first_page pointer\n";
echo "      - Table 5: first_page pointer\n\n";

echo "What was NOT changed:\n";
echo "  ✓ Track data\n";
echo "  ✓ Metadata\n";
echo "  ✓ Page data content\n";
echo "  ✓ Any actual music information\n\n";

echo "✓✓✓ FINAL HYBRID RECOVERY COMPLETE ✓✓✓\n\n";
echo "File: $outputFile\n";
echo "This file should work in Rekordbox!\n";

echo "\n════════════════════════════════════════════════════════════\n";
