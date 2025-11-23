<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Parsers/TrackParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\TrackParser;
use RekordboxReader\Utils\Logger;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFYING ULTRA RECOVERY                                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$normal = file_get_contents('Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb');
$ultra = file_get_contents('plans/export_ultra_recovery.pdb');

echo "═══════════════════════════════════════════════════════════\n";
echo " COMPARISON: Ultra Recovery vs Normal\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Compare headers
$normalHeader = unpack('V6', substr($normal, 0, 24));
$ultraHeader = unpack('V6', substr($ultra, 0, 24));

echo "HEADER COMPARISON:\n";
echo sprintf("  %-20s %-15s %-15s %s\n", "Field", "Normal", "Ultra", "Status");
echo "  " . str_repeat("-", 70) . "\n";

$fields = [
    1 => 'Signature',
    2 => 'Page Size',
    3 => 'Num Tables',
    4 => 'Next Unused Page',
    5 => 'Unknown',
    6 => 'Sequence'
];

$headerMatches = 0;
foreach ($fields as $idx => $name) {
    $match = $normalHeader[$idx] === $ultraHeader[$idx];
    $status = $match ? "✓ MATCH" : "✗ DIFF";
    if ($match) $headerMatches++;
    
    echo sprintf("  %-20s %-15s %-15s %s\n", 
        $name, 
        $normalHeader[$idx], 
        $ultraHeader[$idx],
        $status
    );
}

echo "\n  Header match: $headerMatches / 6 fields\n\n";

// Compare table directory
echo "TABLE DIRECTORY COMPARISON:\n";
$offset = 24;
$tableMatches = 0;
$tableDiffs = 0;

for ($i = 0; $i < 20; $i++) {
    if ($offset + 16 > strlen($normal)) break;
    
    $normalEntry = unpack('V4', substr($normal, $offset, 16));
    $ultraEntry = unpack('V4', substr($ultra, $offset, 16));
    
    $match = (
        $normalEntry[1] === $ultraEntry[1] &&
        $normalEntry[2] === $ultraEntry[2] &&
        $normalEntry[3] === $ultraEntry[3] &&
        $normalEntry[4] === $ultraEntry[4]
    );
    
    if ($match) {
        $tableMatches++;
    } else {
        $tableDiffs++;
        
        if ($tableDiffs <= 5) { // Show first 5 diffs
            echo "  Table $i:\n";
            echo "    Normal: type={$normalEntry[1]} empty={$normalEntry[2]} first={$normalEntry[3]} last={$normalEntry[4]}\n";
            echo "    Ultra:  type={$ultraEntry[1]} empty={$ultraEntry[2]} first={$ultraEntry[3]} last={$ultraEntry[4]}\n";
        }
    }
    
    $offset += 16;
}

echo "\n  Table match: $tableMatches / 20 tables\n";
if ($tableDiffs > 5) {
    echo "  (showing first 5 differences, total: $tableDiffs)\n";
}
echo "\n";

// Overall comparison
echo "OVERALL STRUCTURAL COMPARISON:\n";
$totalDiffs = 0;
$criticalDiffs = 0;

for ($i = 0; $i < min(344, strlen($normal), strlen($ultra)); $i++) {
    if ($normal[$i] !== $ultra[$i]) {
        $totalDiffs++;
        if ($i < 344) $criticalDiffs++;
    }
}

echo "  Different bytes in header+tables: $criticalDiffs / 344 bytes\n";
echo "  Overall similarity: " . round((1 - $criticalDiffs / 344) * 100, 1) . "%\n\n";

// Parse test
echo "═══════════════════════════════════════════════════════════\n";
echo " PARSE TEST\n";
echo "═══════════════════════════════════════════════════════════\n\n";

try {
    $logger = new Logger('output', false);
    $parser = new PdbParser('plans/export_ultra_recovery.pdb', $logger);
    $data = $parser->parse();
    
    echo "✓ Database parsed successfully\n";
    echo "  Tables: " . count($data['tables']) . "\n\n";
    
    $trackParser = new TrackParser($parser, $logger);
    $tracks = $trackParser->parseTracks();
    
    echo "✓ Tracks parsed: " . count($tracks) . " tracks\n\n";
    
    if (count($tracks) > 0) {
        echo "Sample tracks:\n";
        for ($i = 0; $i < min(5, count($tracks)); $i++) {
            $track = $tracks[$i];
            echo sprintf("  %2d. %-50s BPM: %-4s\n",
                $i + 1,
                substr($track['title'] ?? 'N/A', 0, 50),
                $track['bpm'] ?? 'N/A'
            );
        }
    }
    
    echo "\n✓✓✓ FILE IS VALID ✓✓✓\n";
    
} catch (Exception $e) {
    echo "✗ Parse failed: " . $e->getMessage() . "\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo " VERDICT\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if ($headerMatches === 6 && $tableMatches >= 18) {
    echo "✓✓✓ EXCELLENT! Ultra recovery matches normal file structure!\n";
    echo "This file should work in Rekordbox.\n";
} elseif ($headerMatches >= 5 && $criticalDiffs < 50) {
    echo "✓ GOOD! Ultra recovery is very close to normal structure.\n";
    echo "This file has a high chance of working in Rekordbox.\n";
    echo "\nRemaining differences:\n";
    echo "  - Header fields matching: $headerMatches / 6\n";
    echo "  - Table entries matching: $tableMatches / 20\n";
    echo "  - Critical bytes different: $criticalDiffs\n";
} else {
    echo "⚠ Ultra recovery still has some differences.\n";
    echo "May need further refinement.\n";
}

echo "\n════════════════════════════════════════════════════════════\n";
