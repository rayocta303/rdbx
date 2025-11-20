<?php

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';

if (!file_exists($pdbPath)) {
    die("PDB file not found: {$pdbPath}\n");
}

$logger = new Logger(__DIR__ . '/output', false);
$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

echo "=== DEBUG TRACK 2 DETAIL ===\n\n";

function debugAllTracksInTable($pdbParser) {
    $tracksTable = $pdbParser->getTable(0);
    
    if (!$tracksTable) {
        die("Tracks table not found\n");
    }

    $firstPage = $tracksTable['first_page'];
    $lastPage = $tracksTable['last_page'];

    $allTracks = [];

    for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
        $pageData = $pdbParser->readPage($pageIdx);
        if (!$pageData) {
            continue;
        }

        if (strlen($pageData) < 48) {
            continue;
        }

        $pageHeader = unpack(
            'Vgap/' .
            'Vpage_index/' .
            'Vtype/' .
            'Vnext_page/' .
            'Vunknown1/' .
            'Vunknown2/' .
            'Cnum_rows_small/' .
            'Cu3/' .
            'Cu4/' .
            'Cpage_flags/' .
            'vfree_size/' .
            'vused_size/' .
            'vu5/' .
            'vnum_rows_large',
            substr($pageData, 0, 36)
        );

        $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
        
        if (!$isDataPage) {
            continue;
        }

        $numRows = $pageHeader['num_rows_large'];
        if ($numRows == 0 || $numRows == 0x1fff) {
            $numRows = $pageHeader['num_rows_small'];
        }

        if ($numRows == 0) {
            continue;
        }

        $heapPos = 40;
        $pageSize = strlen($pageData);
        
        $rowIndexStart = $pageSize - 4 - (2 * $numRows);
        
        for ($rowIdx = 0; $rowIdx < $numRows; $rowIdx++) {
            $rowOffsetPos = $rowIndexStart + ($rowIdx * 2);
            
            if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                continue;
            }
            
            $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
            $rowOffset = $rowOffsetData[1];
            
            $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
            
            if ($actualRowOffset >= $pageSize || $actualRowOffset + 200 > $pageSize) {
                continue;
            }

            $track = debugTrackRow($pageData, $actualRowOffset, $pdbParser);
            if ($track) {
                $allTracks[] = $track;
            }
        }
    }

    return $allTracks;
}

function debugTrackRow($pageData, $offset, $pdbParser) {
    try {
        if ($offset + 0x94 > strlen($pageData)) {
            return null;
        }
        
        $quickCheck = unpack('V', substr($pageData, $offset, 4));
        if ($quickCheck[1] == 0 || $quickCheck[1] == 0xFFFFFFFF) {
            return null;
        }

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
            'vid/' .           // 0x48 - Track ID
            'vplay_count/' .   // 0x4A
            'vyear/' .         // 0x4C
            'vsample_depth/' . // 0x4E
            'vu19/' .          // 0x50
            'vu20/' .          // 0x52
            'vduration/' .     // 0x54 - Duration in seconds
            'vu21/' .          // 0x56
            'Ccolor_id/' .     // 0x58
            'Crating/' .       // 0x59
            'vkey_id/' .       // 0x5A - Musical Key ID
            'vu22',            // 0x5C
            substr($pageData, $offset, 0x5E)
        );

        $stringOffsets = [];
        $stringBase = $offset + 0x5E;
        
        for ($i = 0; $i < 21; $i++) {
            $strOffsetData = unpack('v', substr($pageData, $stringBase + ($i * 2), 2));
            $stringOffsets[] = $strOffsetData[1];
        }

        $strings = [];
        foreach ($stringOffsets as $idx => $strOffset) {
            if ($strOffset > 0) {
                $absOffset = $offset + $strOffset;
                if ($absOffset < strlen($pageData)) {
                    list($str, $newOffset) = $pdbParser->extractString($pageData, $absOffset);
                    
                    $nullPos = strpos($str, "\x00");
                    if ($nullPos !== false) {
                        $str = substr($str, 0, $nullPos);
                    }
                    
                    $strings[$idx] = $str;
                } else {
                    $strings[$idx] = '';
                }
            } else {
                $strings[$idx] = '';
            }
        }

        return [
            'id' => $fixed['id'],
            'bpm' => round($fixed['tempo'] / 100.0, 2),
            'key_id' => $fixed['key_id'],
            'genre_id' => $fixed['genre_id'],
            'artist_id' => $fixed['artist_id'],
            'album_id' => $fixed['album_id'],
            'strings' => $strings
        ];

    } catch (Exception $e) {
        return null;
    }
}

$tracks = debugAllTracksInTable($pdbParser);

foreach ($tracks as $idx => $track) {
    echo "\n=== TRACK " . ($idx + 1) . " (ID: {$track['id']}) ===\n";
    echo "BPM: {$track['bpm']}\n";
    echo "Key ID: {$track['key_id']}\n";
    echo "Genre ID: {$track['genre_id']}\n";
    echo "Artist ID: {$track['artist_id']}\n";
    echo "Album ID: {$track['album_id']}\n";
    echo "\nAll Strings:\n";
    
    foreach ($track['strings'] as $idx2 => $str) {
        if (!empty($str)) {
            $display = strlen($str) > 80 ? substr($str, 0, 80) . '...' : $str;
            echo "  String[$idx2]: \"" . $display . "\"\n";
        }
    }
}

echo "\n\n=== GENRES TABLE ===\n";
$genresTable = $pdbParser->getTable(1);
if ($genresTable) {
    echo "First Page: {$genresTable['first_page']}, Last Page: {$genresTable['last_page']}\n";
}
