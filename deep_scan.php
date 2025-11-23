<?php

require_once __DIR__ . '/vendor/autoload.php';

use RekordboxReader\Reader;
use RekordboxReader\Utils\DatabaseRecovery;

class DeepScanner {
    private $filePath;
    private $data;
    
    public function __construct($filePath) {
        $this->filePath = $filePath;
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        $this->data = file_get_contents($filePath);
    }
    
    public function scan() {
        echo "========================================\n";
        echo "DEEP SCAN: " . basename($this->filePath) . "\n";
        echo "========================================\n\n";
        
        $this->scanFileBasics();
        $this->scanDatabaseHeader();
        $this->scanPageStructure();
        $this->scanTableDirectory();
        $this->tryReadWithReader();
        
        echo "\n========================================\n";
        echo "SCAN COMPLETE\n";
        echo "========================================\n";
    }
    
    private function scanFileBasics() {
        echo "[1] FILE BASICS\n";
        echo "  Path: {$this->filePath}\n";
        echo "  Size: " . number_format(strlen($this->data)) . " bytes (" . round(strlen($this->data)/1024, 2) . " KB)\n";
        
        // Check if file has minimum size
        if (strlen($this->data) < 4096) {
            echo "  ⚠ WARNING: File too small for valid Rekordbox database\n";
        } else {
            echo "  ✓ File size OK\n";
        }
        echo "\n";
    }
    
    private function scanDatabaseHeader() {
        echo "[2] DATABASE HEADER (0-24 bytes)\n";
        
        if (strlen($this->data) < 24) {
            echo "  ✗ ERROR: File too small for header\n\n";
            return;
        }
        
        // Read header
        $header = unpack('V6', substr($this->data, 0, 24));
        
        $signature = $header[1];
        $pageSize = $header[2];
        $numTables = $header[3];
        $nextUnusedPage = $header[4];
        $unknown = $header[5];
        $sequence = $header[6];
        
        echo "  Signature:        0x" . str_pad(dechex($signature), 8, '0', STR_PAD_LEFT) . "\n";
        echo "  Page Size:        $pageSize bytes\n";
        echo "  Num Tables:       $numTables\n";
        echo "  Next Unused Page: $nextUnusedPage\n";
        echo "  Unknown:          $unknown\n";
        echo "  Sequence:         $sequence\n";
        
        // Validation
        $valid = true;
        
        // Check signature (should be 0x00000000 for Rekordbox)
        if ($signature !== 0) {
            echo "  ✗ Invalid signature (expected 0x00000000)\n";
            $valid = false;
        }
        
        // Check page size (typical: 512, 1024, 2048, 4096, 8192)
        $validPageSizes = [512, 1024, 2048, 4096, 8192];
        if (!in_array($pageSize, $validPageSizes)) {
            echo "  ✗ Invalid page size (expected: " . implode(', ', $validPageSizes) . ")\n";
            $valid = false;
        }
        
        // Check num tables (typical: 10-30)
        if ($numTables < 1 || $numTables > 50) {
            echo "  ✗ Invalid num_tables (expected: 1-50)\n";
            $valid = false;
        }
        
        // Check next_unused_page (should be reasonable)
        $expectedMaxPage = strlen($this->data) / $pageSize;
        if ($nextUnusedPage > $expectedMaxPage * 2) {
            echo "  ⚠ WARNING: next_unused_page ($nextUnusedPage) seems too large (file has ~" . round($expectedMaxPage) . " pages)\n";
            $valid = false;
        }
        
        if ($valid) {
            echo "  ✓ Header structure appears valid\n";
        }
        
        echo "\n";
    }
    
    private function scanPageStructure() {
        echo "[3] PAGE STRUCTURE ANALYSIS\n";
        
        $header = unpack('V6', substr($this->data, 0, 24));
        $pageSize = $header[2];
        
        if ($pageSize < 512 || $pageSize > 8192) {
            echo "  ✗ Cannot scan pages - invalid page size\n\n";
            return;
        }
        
        $totalPages = floor(strlen($this->data) / $pageSize);
        echo "  Total pages: $totalPages (at $pageSize bytes/page)\n";
        
        $validPages = 0;
        $emptyPages = 0;
        $corruptPages = 0;
        
        for ($i = 0; $i < min($totalPages, 100); $i++) {
            $offset = $i * $pageSize;
            $pageData = substr($this->data, $offset, min($pageSize, strlen($this->data) - $offset));
            
            // Check if page is empty (all zeros)
            $isEmpty = true;
            for ($j = 0; $j < min(100, strlen($pageData)); $j++) {
                if (ord($pageData[$j]) !== 0) {
                    $isEmpty = false;
                    break;
                }
            }
            
            if ($isEmpty) {
                $emptyPages++;
            } else {
                // Check if page has valid structure
                if ($this->isPageValid($pageData)) {
                    $validPages++;
                } else {
                    $corruptPages++;
                }
            }
        }
        
        echo "  Valid pages:   $validPages\n";
        echo "  Empty pages:   $emptyPages\n";
        echo "  Corrupt pages: $corruptPages\n";
        
        if ($validPages > 0) {
            echo "  ✓ Found valid pages\n";
        } else {
            echo "  ✗ No valid pages found\n";
        }
        
        echo "\n";
    }
    
    private function isPageValid($pageData) {
        if (strlen($pageData) < 40) return false;
        
        // Check for readable characters
        $readable = 0;
        $checkLen = min(200, strlen($pageData));
        for ($i = 40; $i < $checkLen; $i++) {
            $byte = ord($pageData[$i]);
            if (($byte >= 32 && $byte <= 126) || $byte === 0) {
                $readable++;
            }
        }
        
        return ($readable / ($checkLen - 40)) > 0.3;
    }
    
    private function scanTableDirectory() {
        echo "[4] TABLE DIRECTORY (24+ bytes)\n";
        
        $header = unpack('V6', substr($this->data, 0, 24));
        $numTables = $header[3];
        
        echo "  Expected tables: $numTables\n";
        
        $offset = 24;
        $validTables = 0;
        
        for ($i = 0; $i < $numTables && $offset + 16 <= strlen($this->data); $i++) {
            $tableEntry = unpack('V4', substr($this->data, $offset, 16));
            
            $type = $tableEntry[1];
            $emptyCandidate = $tableEntry[2];
            $firstPage = $tableEntry[3];
            $lastPage = $tableEntry[4];
            
            if ($firstPage > 0 && $lastPage >= $firstPage) {
                $validTables++;
                if ($i < 5) { // Show first 5 tables
                    echo "  Table $i: type=$type, pages=$firstPage-$lastPage\n";
                }
            }
            
            $offset += 16;
        }
        
        echo "  Valid tables:  $validTables / $numTables\n";
        
        if ($validTables > 0) {
            echo "  ✓ Table directory has entries\n";
        } else {
            echo "  ✗ No valid table entries\n";
        }
        
        echo "\n";
    }
    
    private function tryReadWithReader() {
        echo "[5] TRYING TO READ WITH REKORDBOX READER\n";
        
        try {
            $reader = new Reader($this->filePath);
            echo "  ✓ Reader opened file\n";
            
            // Try to get metadata
            try {
                $metadata = $reader->getMetadata();
                echo "  ✓ Got metadata:\n";
                echo "    - Page Size: " . ($metadata['page_size'] ?? 'N/A') . "\n";
                echo "    - Num Tables: " . ($metadata['num_tables'] ?? 'N/A') . "\n";
            } catch (Exception $e) {
                echo "  ✗ Failed to get metadata: " . $e->getMessage() . "\n";
            }
            
            // Try to read tracks
            try {
                $tracks = $reader->getTracks();
                echo "  ✓ Got tracks: " . count($tracks) . " tracks found\n";
                
                if (count($tracks) > 0) {
                    $track = $tracks[0];
                    echo "  ✓ Sample track:\n";
                    echo "    - Title: " . ($track['title'] ?? 'N/A') . "\n";
                    echo "    - Artist: " . ($track['artist'] ?? 'N/A') . "\n";
                }
            } catch (Exception $e) {
                echo "  ✗ Failed to read tracks: " . $e->getMessage() . "\n";
            }
            
        } catch (Exception $e) {
            echo "  ✗ Failed to open with reader: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

// Scan both files
echo "\n";
echo "╔════════════════════════════════════════╗\n";
echo "║  REKORDBOX DATABASE DEEP SCANNER       ║\n";
echo "╚════════════════════════════════════════╝\n";
echo "\n";

$files = [
    'plans/export.pdb' => 'ORIGINAL CORRUPT FILE',
    'plans/export_recovered.pdb' => 'RECOVERED FILE'
];

foreach ($files as $file => $label) {
    if (file_exists($file)) {
        echo "\n";
        echo ">>> $label <<<\n";
        echo "\n";
        
        try {
            $scanner = new DeepScanner($file);
            $scanner->scan();
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}
