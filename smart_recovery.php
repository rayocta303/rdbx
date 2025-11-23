<?php

/**
 * Smart Recovery - Recovery tanpa reference database
 * Analisis mendalam dan fix berdasarkan pattern dan heuristics
 */

class SmartRecovery {
    private $filePath;
    private $outputPath;
    private $data;
    private $fixes = [];
    
    public function __construct($filePath, $outputPath) {
        $this->filePath = $filePath;
        $this->outputPath = $outputPath;
    }
    
    public function recover() {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  SMART RECOVERY (NO REFERENCE)                             ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        
        $this->data = file_get_contents($this->filePath);
        
        echo "File: {$this->filePath}\n";
        echo "Size: " . number_format(strlen($this->data)) . " bytes\n\n";
        
        // Analyze and fix
        $this->analyzeAndFixHeader();
        $this->analyzeAndFixTableDirectory();
        $this->analyzeAndFixPages();
        
        // Save
        $this->save();
        
        // Summary
        $this->showSummary();
        
        return count($this->fixes) > 0;
    }
    
    private function analyzeAndFixHeader() {
        echo "[1] ANALYZING HEADER\n";
        
        $header = unpack('V6', substr($this->data, 0, 24));
        
        $signature = $header[1];
        $pageSize = $header[2];
        $numTables = $header[3];
        $nextUnusedPage = $header[4];
        $unknown = $header[5];
        $sequence = $header[6];
        
        echo "  Current values:\n";
        echo "    Signature:        0x" . str_pad(dechex($signature), 8, '0', STR_PAD_LEFT) . "\n";
        echo "    Page Size:        $pageSize\n";
        echo "    Num Tables:       $numTables\n";
        echo "    Next Unused Page: $nextUnusedPage\n";
        echo "    Unknown:          $unknown\n";
        echo "    Sequence:         $sequence\n\n";
        
        $needsFix = false;
        $newHeader = $header;
        
        // Check signature
        if ($signature !== 0) {
            echo "  ⚠ Signature should be 0x00000000\n";
            $newHeader[1] = 0;
            $needsFix = true;
            $this->fixes[] = "Signature fixed to 0x00000000";
        }
        
        // Check page size
        $validPageSizes = [512, 1024, 2048, 4096, 8192];
        if (!in_array($pageSize, $validPageSizes)) {
            echo "  ⚠ Invalid page size, detecting...\n";
            $detectedSize = $this->detectPageSize();
            $newHeader[2] = $detectedSize;
            $needsFix = true;
            $this->fixes[] = "Page size fixed to $detectedSize";
        } else {
            echo "  ✓ Page size OK\n";
        }
        
        // Check num tables
        if ($numTables < 1 || $numTables > 50) {
            echo "  ⚠ Num tables suspicious, using 20\n";
            $newHeader[3] = 20;
            $needsFix = true;
            $this->fixes[] = "Num tables set to 20";
        } else {
            echo "  ✓ Num tables OK\n";
        }
        
        // Check next_unused_page (calculate from file size)
        $pageSize = $newHeader[2];
        $expectedMaxPage = floor(strlen($this->data) / $pageSize);
        
        if ($nextUnusedPage > $expectedMaxPage * 2 || $nextUnusedPage < 1) {
            echo "  ⚠ Next unused page invalid ($nextUnusedPage), calculating...\n";
            
            // Find the actual last used page by scanning table directory
            $lastUsedPage = $this->findLastUsedPage($pageSize, $newHeader[3]);
            $newNextUnused = $lastUsedPage + 1;
            
            echo "  → Last used page: $lastUsedPage\n";
            echo "  → Setting next unused to: $newNextUnused\n";
            
            $newHeader[4] = $newNextUnused;
            $needsFix = true;
            $this->fixes[] = "Next unused page calculated: $newNextUnused";
        } else {
            echo "  ✓ Next unused page OK\n";
        }
        
        // Update header if needed
        if ($needsFix) {
            $headerBytes = pack('V6', 
                $newHeader[1],
                $newHeader[2],
                $newHeader[3],
                $newHeader[4],
                $newHeader[5],
                $newHeader[6]
            );
            
            for ($i = 0; $i < 24; $i++) {
                $this->data[$i] = $headerBytes[$i];
            }
            
            echo "\n  ✓ Header fixed\n";
        } else {
            echo "\n  ✓ Header OK\n";
        }
        
        echo "\n";
    }
    
    private function detectPageSize() {
        $possibleSizes = [512, 1024, 2048, 4096, 8192];
        
        foreach ($possibleSizes as $size) {
            $totalPages = floor(strlen($this->data) / $size);
            if ($totalPages < 10) continue;
            
            // Check if this size makes sense by looking at page patterns
            $validCount = 0;
            for ($i = 1; $i < min(10, $totalPages); $i++) {
                $offset = $i * $size;
                if ($offset + 8 < strlen($this->data)) {
                    $bytes = substr($this->data, $offset, 8);
                    // Check for non-zero patterns
                    $hasData = false;
                    for ($j = 0; $j < 8; $j++) {
                        if (ord($bytes[$j]) !== 0) {
                            $hasData = true;
                            break;
                        }
                    }
                    if ($hasData) $validCount++;
                }
            }
            
            if ($validCount >= 5) {
                return $size;
            }
        }
        
        return 4096; // Default
    }
    
    private function findLastUsedPage($pageSize, $numTables) {
        $offset = 24; // Start of table directory
        $maxPage = 0;
        
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($this->data)) break;
            
            $tableEntry = unpack('V4', substr($this->data, $offset, 16));
            $type = $tableEntry[1];
            $firstPage = $tableEntry[3];
            $lastPage = $tableEntry[4];
            
            // Only count if values seem reasonable
            if ($lastPage > 0 && $lastPage < 10000) {
                $maxPage = max($maxPage, $lastPage);
            }
            
            $offset += 16;
        }
        
        // If we couldn't find from table directory, scan file
        if ($maxPage == 0) {
            $maxPage = floor(strlen($this->data) / $pageSize) - 1;
        }
        
        return $maxPage;
    }
    
    private function analyzeAndFixTableDirectory() {
        echo "[2] ANALYZING TABLE DIRECTORY\n";
        
        $header = unpack('V6', substr($this->data, 0, 24));
        $numTables = $header[3];
        $pageSize = $header[2];
        
        echo "  Checking $numTables table entries...\n\n";
        
        $offset = 24;
        $fixedTables = 0;
        
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($this->data)) break;
            
            $tableEntry = unpack('V4', substr($this->data, $offset, 16));
            $type = $tableEntry[1];
            $emptyCandidate = $tableEntry[2];
            $firstPage = $tableEntry[3];
            $lastPage = $tableEntry[4];
            
            $needsTableFix = false;
            $newEntry = $tableEntry;
            
            // Check if first_page is unreasonably large
            $maxReasonablePage = strlen($this->data) / $pageSize;
            
            if ($firstPage > $maxReasonablePage) {
                echo "  Table $i: first_page=$firstPage is too large (max: " . round($maxReasonablePage) . ")\n";
                
                // Try to find actual page by scanning
                $actualPage = $this->findTablePage($type, $pageSize);
                if ($actualPage > 0) {
                    $newEntry[3] = $actualPage;
                    $newEntry[4] = $actualPage; // Same for now
                    echo "    → Fixed to: $actualPage\n";
                    $needsTableFix = true;
                }
            }
            
            // Check if last_page < first_page
            if ($lastPage > 0 && $lastPage < $firstPage && $firstPage < $maxReasonablePage) {
                // Swap them
                $temp = $newEntry[3];
                $newEntry[3] = $newEntry[4];
                $newEntry[4] = $temp;
                echo "  Table $i: Swapped first/last pages\n";
                $needsTableFix = true;
            }
            
            if ($needsTableFix) {
                $entryBytes = pack('V4', $newEntry[1], $newEntry[2], $newEntry[3], $newEntry[4]);
                for ($j = 0; $j < 16; $j++) {
                    $this->data[$offset + $j] = $entryBytes[$j];
                }
                $fixedTables++;
                $this->fixes[] = "Table $i directory entry fixed";
            }
            
            $offset += 16;
        }
        
        if ($fixedTables > 0) {
            echo "\n  ✓ Fixed $fixedTables table entries\n";
        } else {
            echo "\n  ✓ Table directory OK\n";
        }
        
        echo "\n";
    }
    
    private function findTablePage($type, $pageSize) {
        // Scan pages to find one that might belong to this table type
        $totalPages = floor(strlen($this->data) / $pageSize);
        
        for ($page = 1; $page < min($totalPages, 100); $page++) {
            $offset = $page * $pageSize;
            if ($offset + 100 > strlen($this->data)) continue;
            
            $pageData = substr($this->data, $offset, 100);
            
            // Simple heuristic: check if page has data
            $hasData = false;
            for ($i = 40; $i < 100; $i++) {
                if (ord($pageData[$i]) > 0) {
                    $hasData = true;
                    break;
                }
            }
            
            if ($hasData) {
                return $page;
            }
        }
        
        return 0;
    }
    
    private function analyzeAndFixPages() {
        echo "[3] ANALYZING PAGES\n";
        
        $header = unpack('V6', substr($this->data, 0, 24));
        $pageSize = $header[2];
        $totalPages = floor(strlen($this->data) / $pageSize);
        
        echo "  Page size: $pageSize\n";
        echo "  Total pages: $totalPages\n";
        
        $corruptPages = 0;
        $fixedPages = 0;
        
        // Don't fix pages, just count corrupt ones
        for ($page = 0; $page < min($totalPages, 100); $page++) {
            $offset = $page * $pageSize;
            if ($offset + 40 > strlen($this->data)) continue;
            
            $pageHeader = substr($this->data, $offset, 40);
            
            // Check if header looks corrupt (all zeros or all 0xFF)
            $allZero = true;
            $allFF = true;
            
            for ($i = 0; $i < 40; $i++) {
                $byte = ord($pageHeader[$i]);
                if ($byte !== 0) $allZero = false;
                if ($byte !== 0xFF) $allFF = false;
            }
            
            if (($allZero || $allFF) && $page > 0) {
                $corruptPages++;
            }
        }
        
        echo "  Corrupt pages detected: $corruptPages\n";
        echo "  ✓ Pages analyzed (no changes - preserving data)\n";
        echo "\n";
    }
    
    private function save() {
        echo "[4] SAVING RECOVERED FILE\n";
        
        $dir = dirname($this->outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->outputPath, $this->data);
        
        echo "  ✓ Saved to: {$this->outputPath}\n";
        echo "  Size: " . number_format(strlen($this->data)) . " bytes\n\n";
    }
    
    private function showSummary() {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  RECOVERY SUMMARY                                          ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        
        if (count($this->fixes) > 0) {
            echo "Fixes applied:\n";
            foreach ($this->fixes as $idx => $fix) {
                echo "  " . ($idx + 1) . ". $fix\n";
            }
            echo "\nTotal fixes: " . count($this->fixes) . "\n";
            echo "\n✓✓✓ RECOVERY COMPLETE ✓✓✓\n";
        } else {
            echo "No fixes needed - file structure appears valid\n";
            echo "\n✓ ANALYSIS COMPLETE\n";
        }
        
        echo "\nOutput file: {$this->outputPath}\n";
    }
}

// Run smart recovery
$corruptFile = 'plans/export.pdb';
$outputFile = 'plans/export_smart_recovery.pdb';

echo "\n";

try {
    $recovery = new SmartRecovery($corruptFile, $outputFile);
    $recovery->recover();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
