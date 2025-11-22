<?php

spl_autoload_register(function ($class) {
    $prefix = 'RekordboxReader\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use RekordboxReader\Parsers\PdbParser;

function hexDump($data, $offset, $length) {
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        if ($i % 16 == 0) {
            $result .= sprintf("\n%04X: ", $offset + $i);
        }
        if ($offset + $i < strlen($data)) {
            $byte = ord($data[$offset + $i]);
            $result .= sprintf("%02X ", $byte);
            if (($i + 1) % 16 == 0) {
                $result .= " | ";
                for ($j = $i - 15; $j <= $i; $j++) {
                    if ($offset + $j < strlen($data)) {
                        $ch = ord($data[$offset + $j]);
                        $result .= ($ch >= 32 && $ch < 127) ? chr($ch) : '.';
                    }
                }
            }
        }
    }
    return $result;
}

$pdbPath = './Rekordbox-USB/PIONEER/rekordbox/export.pdb';

echo "=== DEBUGGING PLAYLIST BINARY DATA ===\n\n";

$parser = new PdbParser($pdbPath);
$parser->parse();

$playlistTree = $parser->getTable(PdbParser::TABLE_PLAYLIST_TREE);

if (!$playlistTree) {
    echo "No playlist tree table found!\n";
    exit(1);
}

echo "Playlist Tree Table: First Page = {$playlistTree['first_page']}, Last Page = {$playlistTree['last_page']}\n\n";

// Read first data pages - scan more to find actual data
for ($pageIdx = $playlistTree['first_page']; $pageIdx <= min($playlistTree['first_page'] + 10, $playlistTree['last_page']); $pageIdx++) {
    $pageData = $parser->readPage($pageIdx);
    
    if (!$pageData) {
        echo "Page $pageIdx: NULL\n";
        continue;
    }
    
    echo "=== Page $pageIdx ===\n";
    
    // Parse page header
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
    
    echo "Page Index: {$pageHeader['page_index']}, Type: {$pageHeader['type']}, Data Page: " . ($isDataPage ? 'Yes' : 'No') . "\n";
    echo "Num Rows Small: {$pageHeader['num_rows_small']}, Num Rows Large: {$pageHeader['num_rows_large']}\n";
    echo "Page Flags: 0x" . dechex($pageHeader['page_flags']) . "\n";
    
    if (!$isDataPage) {
        echo "Skipping non-data page\n\n";
        continue;
    }
    
    $numRows = $pageHeader['num_rows_small'];
    if ($pageHeader['num_rows_large'] > $pageHeader['num_rows_small'] && 
        $pageHeader['num_rows_large'] != 0x1fff) {
        $numRows = $pageHeader['num_rows_large'];
    }
    
    echo "Total Rows: $numRows\n";
    
    $heapPos = 40;
    $pageSize = strlen($pageData);
    $numGroups = intval(($numRows - 1) / 16) + 1;
    
    echo "Heap Position: $heapPos, Page Size: $pageSize, Num Groups: $numGroups\n\n";
    
    $rowCount = 0;
    for ($groupIdx = 0; $groupIdx < min($numGroups, 2); $groupIdx++) {
        $base = $pageSize - ($groupIdx * 0x24);
        $flagsOffset = $base - 4;
        
        echo "Group $groupIdx: Base = $base, Flags Offset = $flagsOffset\n";
        
        if ($flagsOffset < 0 || $flagsOffset + 2 > $pageSize) {
            continue;
        }
        
        $presenceFlags = unpack('v', substr($pageData, $flagsOffset, 2))[1];
        $rowsInGroup = min(16, $numRows - ($groupIdx * 16));
        
        echo "Presence Flags: 0x" . dechex($presenceFlags) . ", Rows in Group: $rowsInGroup\n";
        
        // Skip if no rows present in this group
        if ($presenceFlags == 0) {
            echo "  No present rows in this group\n";
            continue;
        }
        
        for ($rowIdx = 0; $rowIdx < min($rowsInGroup, 5); $rowIdx++) {
            $rowOffsetPos = $base - (6 + ($rowIdx * 2));
            
            if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
                continue;
            }
            
            $rowOffsetData = unpack('v', substr($pageData, $rowOffsetPos, 2));
            $rowOffset = $rowOffsetData[1];
            
            $present = (($presenceFlags >> $rowIdx) & 1) != 0;
            
            if (!$present) {
                echo "  Row $rowIdx: NOT PRESENT\n";
                continue;
            }
            
            $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
            
            echo "\n  === Row $rowIdx (Row Count: $rowCount) ===\n";
            echo "  Row Offset: 0x" . dechex($rowOffset) . " => Actual: 0x" . dechex($actualRowOffset) . " ($actualRowOffset)\n";
            
            if ($actualRowOffset + 50 > $pageSize) {
                echo "  INVALID: Exceeds page size\n";
                continue;
            }
            
            // Show hex dump of first 80 bytes of row
            echo hexDump($pageData, $actualRowOffset, min(80, $pageSize - $actualRowOffset));
            
            // Try to parse as playlist row
            echo "\n\n  Parsing as playlist_tree_row:\n";
            
            $fixed = unpack(
                'Vparent_id/' .
                'Vunknown/' .
                'Vsort_order/' .
                'Vid/' .
                'Vraw_is_folder',
                substr($pageData, $actualRowOffset, 20)
            );
            
            echo "  Parent ID: {$fixed['parent_id']}\n";
            echo "  Unknown: {$fixed['unknown']}\n";
            echo "  Sort Order: {$fixed['sort_order']}\n";
            echo "  ID: {$fixed['id']}\n";
            echo "  Is Folder: {$fixed['raw_is_folder']}\n";
            
            // Try to extract string at offset +20
            $nameOffset = $actualRowOffset + 20;
            echo "\n  String at offset +20 (byte $nameOffset):\n";
            echo "  Hex dump of string data:";
            echo hexDump($pageData, $nameOffset, min(40, $pageSize - $nameOffset));
            
            list($name, $newOffset) = $parser->extractString($pageData, $nameOffset);
            echo "\n  Extracted Name: '$name'\n";
            echo "  Name Length: " . strlen($name) . "\n";
            
            $rowCount++;
            echo "\n";
        }
    }
    
    echo "\n";
}
