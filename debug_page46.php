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

$pdbPath = './Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$parser = new PdbParser($pdbPath);
$parser->parse();

echo "=== DETAILED ANALYSIS OF PAGE 46 ===\n\n";

$pageData = $parser->readPage(46);

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

$numRows = $pageHeader['num_rows_small'];
if ($pageHeader['num_rows_large'] > $pageHeader['num_rows_small'] && 
    $pageHeader['num_rows_large'] != 0x1fff) {
    $numRows = $pageHeader['num_rows_large'];
}

echo "Page 46 Header:\n";
echo "  Type: {$pageHeader['type']}\n";
echo "  Num Rows Small: {$pageHeader['num_rows_small']}\n";
echo "  Num Rows Large: {$pageHeader['num_rows_large']}\n";
echo "  Total Rows: $numRows\n\n";

$heapPos = 40;
$pageSize = strlen($pageData);
$numGroups = intval(($numRows - 1) / 16) + 1;

echo "Num Groups: $numGroups\n\n";

for ($groupIdx = 0; $groupIdx < $numGroups; $groupIdx++) {
    $base = $pageSize - ($groupIdx * 0x24);
    $flagsOffset = $base - 4;
    
    $presenceFlags = unpack('v', substr($pageData, $flagsOffset, 2))[1];
    $rowsInGroup = min(16, $numRows - ($groupIdx * 16));
    
    echo "Group $groupIdx: Presence=0x" . dechex($presenceFlags) . " (binary: " . decbin($presenceFlags) . ")\n";
    
    for ($rowIdx = 0; $rowIdx < $rowsInGroup; $rowIdx++) {
        $rowOffsetPos = $base - (6 + ($rowIdx * 2));
        
        if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) {
            continue;
        }
        
        $rowOffset = unpack('v', substr($pageData, $rowOffsetPos, 2))[1];
        $present = (($presenceFlags >> $rowIdx) & 1) != 0;
        
        $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
        
        echo "  Row $rowIdx: Present=" . ($present ? 'YES' : 'NO') . ", Offset=0x" . dechex($rowOffset) . " => $actualRowOffset";
        
        if ($present && $actualRowOffset + 20 < $pageSize) {
            $fixed = unpack(
                'Vparent_id/' .
                'Vunknown/' .
                'Vsort_order/' .
                'Vid/' .
                'Vraw_is_folder',
                substr($pageData, $actualRowOffset, 20)
            );
            
            $nameOffset = $actualRowOffset + 20;
            list($name, $newOffset) = $parser->extractString($pageData, $nameOffset);
            $name = trim($name);
            
            echo " => ID={$fixed['id']}, Parent={$fixed['parent_id']}, Sort={$fixed['sort_order']}, Folder={$fixed['raw_is_folder']}, Name='$name'";
        }
        
        echo "\n";
    }
    echo "\n";
}
