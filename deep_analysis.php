<?php

/**
 * Deep Analysis - Understand WHY file is still corrupt in Rekordbox
 * Even though it parses in PHP
 */

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  DEEP ANALYSIS - Why Still Corrupt in Rekordbox?          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$corruptFile = 'plans/export.pdb';
$normalFile = 'Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb';
$recoveredFile = 'plans/export_smart_recovery.pdb';

function analyzeFile($filePath, $label) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo " $label\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    $data = file_get_contents($filePath);
    
    // Header analysis
    $header = unpack('V6', substr($data, 0, 24));
    echo "HEADER (first 24 bytes):\n";
    echo "  Signature:        0x" . str_pad(dechex($header[1]), 8, '0', STR_PAD_LEFT) . "\n";
    echo "  Page Size:        {$header[2]}\n";
    echo "  Num Tables:       {$header[3]}\n";
    echo "  Next Unused Page: {$header[4]}\n";
    echo "  Unknown:          {$header[5]}\n";
    echo "  Sequence:         {$header[6]}\n\n";
    
    $pageSize = $header[2];
    $numTables = $header[3];
    
    // Table directory analysis
    echo "TABLE DIRECTORY:\n";
    $offset = 24;
    $corruptTables = 0;
    
    for ($i = 0; $i < min($numTables, 20); $i++) {
        if ($offset + 16 > strlen($data)) break;
        
        $tableEntry = unpack('V4', substr($data, $offset, 16));
        $type = $tableEntry[1];
        $emptyCandidate = $tableEntry[2];
        $firstPage = $tableEntry[3];
        $lastPage = $tableEntry[4];
        
        $status = "OK";
        if ($firstPage > 10000 || $lastPage > 10000) {
            $status = "✗ CORRUPT (pages > 10000)";
            $corruptTables++;
        } elseif ($lastPage < $firstPage && $firstPage < 1000) {
            $status = "✗ SWAPPED";
            $corruptTables++;
        } elseif ($firstPage == 0 || $lastPage == 0) {
            $status = "✗ ZERO";
            $corruptTables++;
        }
        
        echo sprintf("  Table %2d: type=%-4d empty=%-4d first=%-8d last=%-8d %s\n", 
            $i, $type, $emptyCandidate, $firstPage, $lastPage, $status);
        
        $offset += 16;
    }
    
    echo "\n  Corrupt tables: $corruptTables / $numTables\n\n";
    
    // Page headers analysis (first 10 pages)
    echo "PAGE HEADERS (first 10 pages):\n";
    $corruptPages = 0;
    
    for ($page = 0; $page < min(10, floor(strlen($data) / $pageSize)); $page++) {
        $pageOffset = $page * $pageSize;
        $pageHeader = substr($data, $pageOffset, 40);
        
        // Check if all zeros
        $allZero = true;
        for ($i = 0; $i < 40; $i++) {
            if (ord($pageHeader[$i]) !== 0) {
                $allZero = false;
                break;
            }
        }
        
        // Check if all 0xFF
        $allFF = true;
        for ($i = 0; $i < 40; $i++) {
            if (ord($pageHeader[$i]) !== 0xFF) {
                $allFF = false;
                break;
            }
        }
        
        $status = "OK";
        if ($allZero && $page > 0) {
            $status = "✗ ALL ZEROS";
            $corruptPages++;
        } elseif ($allFF) {
            $status = "✗ ALL 0xFF";
            $corruptPages++;
        }
        
        // Show first 16 bytes in hex
        $hex = '';
        for ($i = 0; $i < min(16, strlen($pageHeader)); $i++) {
            $hex .= str_pad(dechex(ord($pageHeader[$i])), 2, '0', STR_PAD_LEFT) . ' ';
        }
        
        echo sprintf("  Page %2d: %s... %s\n", $page, $hex, $status);
    }
    
    echo "\n  Corrupt page headers: $corruptPages / 10\n\n";
    
    // Check first 1024 bytes in detail
    echo "CRITICAL BYTES (positions where corruption is common):\n";
    $criticalPositions = [
        4 => 'Sequence (alt)',
        20 => 'Unknown field',
        24 => 'Table 0 type',
        28 => 'Table 0 empty',
        32 => 'Table 0 first page',
        36 => 'Table 0 last page',
    ];
    
    foreach ($criticalPositions as $pos => $desc) {
        if ($pos + 4 <= strlen($data)) {
            $value = unpack('V', substr($data, $pos, 4))[1];
            echo sprintf("  Pos %4d: 0x%08x (%10d) - %s\n", $pos, $value, $value, $desc);
        }
    }
    
    echo "\n";
}

// Analyze all files
analyzeFile($normalFile, "NORMAL FILE (reference for understanding)");
analyzeFile($corruptFile, "CORRUPT FILE (original)");
analyzeFile($recoveredFile, "RECOVERED FILE (smart recovery)");

echo "═══════════════════════════════════════════════════════════\n";
echo " COMPARISON: What's Still Wrong?\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$normal = file_get_contents($normalFile);
$corrupt = file_get_contents($corruptFile);
$recovered = file_get_contents($recoveredFile);

echo "Comparing RECOVERED vs NORMAL (first 1024 bytes):\n\n";

$differences = 0;
$criticalDiffs = [];

for ($i = 0; $i < min(1024, strlen($normal), strlen($recovered)); $i++) {
    if ($normal[$i] !== $recovered[$i]) {
        $differences++;
        
        // Track critical differences (header and table directory)
        if ($i < 344) { // 24 + (20 * 16)
            $criticalDiffs[] = [
                'pos' => $i,
                'normal' => ord($normal[$i]),
                'recovered' => ord($recovered[$i])
            ];
        }
    }
}

echo "Total different bytes: $differences\n";
echo "Critical differences (header + table directory): " . count($criticalDiffs) . "\n\n";

if (count($criticalDiffs) > 0 && count($criticalDiffs) <= 50) {
    echo "Critical differences detail:\n";
    foreach ($criticalDiffs as $diff) {
        $section = "unknown";
        if ($diff['pos'] < 24) {
            $section = "HEADER";
        } elseif ($diff['pos'] < 344) {
            $tableIdx = floor(($diff['pos'] - 24) / 16);
            $fieldIdx = ($diff['pos'] - 24) % 16;
            $fields = ['type', 'type', 'type', 'type', 'empty', 'empty', 'empty', 'empty', 
                       'first', 'first', 'first', 'first', 'last', 'last', 'last', 'last'];
            $section = "TABLE $tableIdx - " . ($fields[$fieldIdx] ?? '?');
        }
        
        echo sprintf("  Pos %4d (%s): normal=0x%02x recovered=0x%02x\n",
            $diff['pos'], $section, $diff['normal'], $diff['recovered']);
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo " DIAGNOSIS\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Issues that might cause Rekordbox to reject the file:\n\n";

$normalHeader = unpack('V6', substr($normal, 0, 24));
$recoveredHeader = unpack('V6', substr($recovered, 0, 24));

$issues = [];

if ($recoveredHeader[6] !== $normalHeader[6]) {
    $issues[] = "✗ Sequence number mismatch: recovered={$recoveredHeader[6]}, should be ~{$normalHeader[6]}";
}

if ($recoveredHeader[4] !== $normalHeader[4]) {
    $issues[] = "⚠ Next unused page different: recovered={$recoveredHeader[4]}, normal={$normalHeader[4]}";
}

if (count($criticalDiffs) > 0) {
    $issues[] = "✗ " . count($criticalDiffs) . " bytes different in critical section (header + table directory)";
}

if (count($issues) > 0) {
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
} else {
    echo "  ✓ No obvious structural issues found\n";
    echo "  → Problem might be in page data or checksums\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo " RECOMMENDATION\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "The recovered file parses in PHP but Rekordbox rejects it.\n";
echo "This suggests:\n\n";
echo "1. Rekordbox performs additional integrity checks\n";
echo "2. Page headers or page data still have issues\n";
echo "3. Sequence number might be critical\n";
echo "4. There might be checksums we haven't considered\n\n";

echo "Next steps:\n";
echo "  → Check if sequence number needs to match\n";
echo "  → Verify all page headers are correct\n";
echo "  → Check if there are checksums in the file\n";

echo "\n════════════════════════════════════════════════════════════\n";
