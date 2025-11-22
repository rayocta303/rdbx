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

$playlistTree = $parser->getTable(PdbParser::TABLE_PLAYLIST_TREE);

echo "=== SCANNING ALL PLAYLIST PAGES ===\n\n";
echo "First Page: {$playlistTree['first_page']}, Last Page: {$playlistTree['last_page']}\n\n";

// Scan all pages in the playlist tree table
for ($pageIdx = $playlistTree['first_page']; $pageIdx <= $playlistTree['last_page']; $pageIdx++) {
    $pageData = $parser->readPage($pageIdx);
    
    if (!$pageData || strlen($pageData) < 36) {
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
    
    if (!$isDataPage || $pageHeader['type'] != 7) {
        continue;
    }
    
    $numRows = $pageHeader['num_rows_small'];
    if ($pageHeader['num_rows_large'] > $pageHeader['num_rows_small'] && 
        $pageHeader['num_rows_large'] != 0x1fff) {
        $numRows = $pageHeader['num_rows_large'];
    }
    
    if ($numRows == 0) {
        continue;
    }
    
    $heapPos = 40;
    $pageSize = strlen($pageData);
    $numGroups = intval(($numRows - 1) / 16) + 1;
    
    $base = $pageSize - 4;
    $presenceFlags = unpack('v', substr($pageData, $base, 2))[1];
    
    // Only show pages with actual present rows
    if ($presenceFlags > 0) {
        echo "Page $pageIdx: Type={$pageHeader['type']}, Rows=$numRows, Presence=0x" . dechex($presenceFlags) . "\n";
        
        // Show first few present rows
        for ($rowIdx = 0; $rowIdx < min($numRows, 16); $rowIdx++) {
            $present = (($presenceFlags >> $rowIdx) & 1) != 0;
            if (!$present) continue;
            
            $rowOffsetPos = $base - (6 + ($rowIdx * 2));
            if ($rowOffsetPos < 0 || $rowOffsetPos + 2 > $pageSize) continue;
            
            $rowOffset = unpack('v', substr($pageData, $rowOffsetPos, 2))[1];
            $actualRowOffset = ($rowOffset & 0x1FFF) + $heapPos;
            
            if ($actualRowOffset + 20 > $pageSize) continue;
            
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
            
            $isFolder = $fixed['raw_is_folder'] != 0 ? 'Folder' : 'Playlist';
            
            echo "  Row $rowIdx: ID={$fixed['id']}, Parent={$fixed['parent_id']}, Type=$isFolder, Name='$name'\n";
        }
        echo "\n";
    }
}

echo "\n=== Done ===\n";
