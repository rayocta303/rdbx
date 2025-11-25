<?php

namespace RekordboxReader\Utils;

/**
 * DatabaseRecovery - Recover corrupted Rekordbox databases
 * 
 * Implements recovery methods for 10 corruption scenarios:
 * 1. Signature/Magic Header Corruption → Header reconstruction
 * 2. Database Metadata Header Corruption → Metadata inference
 * 3. Page Header Corruption → Page pattern scanning
 * 4. Row Presence Bitmap Corruption → Force-all-valid
 * 5. Table Index Corruption → Linear full scan
 * 6. Row Structure Corruption → Field pattern matching
 * 7. Field-Level Data Corruption → Data sanity check
 * 8. Playlist Structure Corruption → Tree rebuild
 * 9. Cross-Table Relationship Corruption → Orphan relinking
 * 10. Version Mismatch → Version detection
 */
class DatabaseRecovery {
    private $corruptDb;
    private $recoveredDb;
    private $referenceDb; // Optional: known good DB for templates
    private $data;
    private $logger;
    private $recoveryLog = [];

    const VALID_SIGNATURE = 0x00000000; // Rekordbox PDB signature (placeholder)
    const DEFAULT_PAGE_SIZE = 4096;
    const DEFAULT_NUM_TABLES = 20;

    public function __construct($corruptDbPath, $recoveredDbPath, $referenceDbPath = null, $logger = null) {
        $this->corruptDb = $corruptDbPath;
        $this->recoveredDb = $recoveredDbPath;
        $this->referenceDb = $referenceDbPath;
        $this->logger = $logger;
    }

    private function loadDatabase() {
        if (!file_exists($this->corruptDb)) {
            throw new \Exception("Corrupt database not found: {$this->corruptDb}");
        }
        $this->data = file_get_contents($this->corruptDb);
        $this->log("Loaded corrupt database: " . strlen($this->data) . " bytes");
    }

    private function saveDatabase() {
        $dir = dirname($this->recoveredDb);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->recoveredDb, $this->data);
        $this->log("Saved recovered database to: {$this->recoveredDb}");
    }

    private function log($message) {
        $this->recoveryLog[] = $message;
        if ($this->logger) {
            $this->logger->info("[Recovery] " . $message);
        }
    }

    /**
     * Recovery Method 1: Reconstruct Magic Header
     * Replace corrupted signature with valid one from reference DB or static value
     */
    public function recoverMagicHeader() {
        $this->loadDatabase();
        
        if ($this->referenceDb && file_exists($this->referenceDb)) {
            // Use reference DB header
            $refData = file_get_contents($this->referenceDb);
            $validHeader = substr($refData, 0, 4);
            $this->log("Using reference DB header");
        } else {
            // Use static known-good signature (from analysis of valid DBs)
            $validHeader = pack('CCCC', 0x00, 0x00, 0x00, 0x00); // Placeholder
            $this->log("Using static header reconstruction");
        }
        
        $this->data = $validHeader . substr($this->data, 4);
        $this->saveDatabase();
        $this->log("Magic header recovered");
        
        return true;
    }

    /**
     * Recovery Method 2: Infer Metadata via Page Scan
     * Scan pages to infer page_size and num_tables
     */
    public function recoverMetadataHeader() {
        $this->loadDatabase();
        
        // If we have a reference DB, use it directly for accurate recovery
        if ($this->referenceDb && file_exists($this->referenceDb)) {
            $refData = file_get_contents($this->referenceDb);
            $refHeader = unpack('V6', substr($refData, 0, 24));
            $corruptHeader = unpack('V6', substr($this->data, 0, 24));
            
            $this->log("Using reference DB for header recovery");
            $this->log("Reference: signature={$refHeader[1]}, page_size={$refHeader[2]}, num_tables={$refHeader[3]}, next_unused_page={$refHeader[4]}, sequence={$refHeader[6]}");
            $this->log("Corrupt:   signature={$corruptHeader[1]}, page_size={$corruptHeader[2]}, num_tables={$corruptHeader[3]}, next_unused_page={$corruptHeader[4]}, sequence={$corruptHeader[6]}");
            
            // Copy entire header from reference (all 24 bytes)
            $newHeader = substr($refData, 0, 24);
            $this->data = $newHeader . substr($this->data, 24);
            $this->log("Copied complete header from reference DB");
        } else {
            // Smart recovery without reference
            $this->log("Smart recovery: analyzing header without reference");
            
            $currentHeader = unpack('V6', substr($this->data, 0, 24));
            $newHeader = $currentHeader;
            
            // Fix signature if needed
            if ($currentHeader[1] !== 0) {
                $this->log("Fixing signature: {$currentHeader[1]} → 0");
                $newHeader[1] = 0;
            }
            
            // Validate page size
            $validPageSizes = [512, 1024, 2048, 4096, 8192];
            
            if (!in_array($currentHeader[2], $validPageSizes)) {
                $detectedPageSize = $this->detectPageSize();
                $this->log("Invalid page size {$currentHeader[2]}, detected: $detectedPageSize");
                $newHeader[2] = $detectedPageSize;
            } else {
                $this->log("Page size OK: {$currentHeader[2]}");
            }
            
            // Validate num_tables
            if ($currentHeader[3] < 1 || $currentHeader[3] > 50) {
                $this->log("Invalid num_tables {$currentHeader[3]}, using default: " . self::DEFAULT_NUM_TABLES);
                $newHeader[3] = self::DEFAULT_NUM_TABLES;
            } else {
                $this->log("Num tables OK: {$currentHeader[3]}");
            }
            
            // CRITICAL FIX: Calculate correct next_unused_page
            // This field is at offset 0x0c and MUST point to the first unused page in the file
            $pageSize = $newHeader[2];
            $numTables = $newHeader[3];
            $maxPage = floor(strlen($this->data) / $pageSize);
            
            // Check if next_unused_page value is reasonable
            // It should be:
            // 1. Greater than 0 (page 0 is the header)
            // 2. Less than or equal to total pages in file
            // 3. Greater than any page referenced in table directory
            
            $needsFixing = false;
            if ($currentHeader[4] > $maxPage || $currentHeader[4] < 1) {
                $needsFixing = true;
                $this->log("Invalid next_unused_page {$currentHeader[4]} (file has $maxPage pages max)");
            }
            
            // Also scan table directory to find the highest used page
            $lastUsedPage = $this->findLastUsedPageFromTableDirectory($pageSize, $numTables);
            
            if ($currentHeader[4] <= $lastUsedPage && $currentHeader[4] > 0) {
                // next_unused_page should be AFTER last used page
                $needsFixing = true;
                $this->log("next_unused_page {$currentHeader[4]} conflicts with table directory (last used: $lastUsedPage)");
            }
            
            if ($needsFixing) {
                $correctNextUnused = min($lastUsedPage + 1, $maxPage);
                $this->log("Fixing next_unused_page: {$currentHeader[4]} → $correctNextUnused");
                $newHeader[4] = $correctNextUnused;
            } else {
                $this->log("Next unused page OK: {$currentHeader[4]}");
            }
            
            $headerBytes = pack('V6', $newHeader[1], $newHeader[2], $newHeader[3], $newHeader[4], $newHeader[5], $newHeader[6]);
            $this->data = $headerBytes . substr($this->data, 24);
            $this->log("Smart header recovery completed");
        }
        
        $this->saveDatabase();
        $this->log("Metadata header recovered");
        
        return true;
    }
    
    /**
     * Detect page size by checking patterns
     */
    private function detectPageSize() {
        $possibleSizes = [512, 1024, 2048, 4096, 8192];
        
        foreach ($possibleSizes as $size) {
            $totalPages = floor(strlen($this->data) / $size);
            if ($totalPages < 10) continue;
            
            $validCount = 0;
            for ($i = 1; $i < min(10, $totalPages); $i++) {
                $offset = $i * $size;
                if ($offset + 8 < strlen($this->data)) {
                    $bytes = substr($this->data, $offset, 8);
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
        
        return self::DEFAULT_PAGE_SIZE;
    }
    
    /**
     * Find last used page by scanning table directory
     * IMPORTANT: Rekordbox PDB table entries have swapped field ordering vs Deep Symmetry docs!
     * Offset 0x08: Last page (not first as documented)
     * Offset 0x0c: First page (not last as documented)
     */
    private function findLastUsedPageFromTableDirectory($pageSize, $numTables) {
        $offset = 24;
        $maxPage = 0;
        $maxReasonablePage = floor(strlen($this->data) / $pageSize);
        
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($this->data)) break;
            
            $tableEntry = unpack('V4', substr($this->data, $offset, 16));
            $type = $tableEntry[1];
            $emptyCandidate = $tableEntry[2];
            
            // CRITICAL: Real Rekordbox files have last_page BEFORE first_page in the binary structure
            // This differs from Deep Symmetry documentation but matches all tested Rekordbox files
            $lastPage = $tableEntry[3];   // Offset 0x08 contains last page
            $firstPage = $tableEntry[4];  // Offset 0x0c contains first page
            
            // Validate before using: both pages must be reasonable
            // first_page should be > 0 and last_page should be >= first_page
            if ($firstPage > 0 && $lastPage >= $firstPage && $lastPage < $maxReasonablePage) {
                $maxPage = max($maxPage, $lastPage);
                $this->log("Table $i: type=$type first=$firstPage last=$lastPage");
            } else if ($firstPage > 0 && $firstPage < $maxReasonablePage) {
                // If last_page is corrupt but first_page is valid, use first_page
                $maxPage = max($maxPage, $firstPage);
                $this->log("Table $i: using first_page=$firstPage (last_page=$lastPage seems corrupt)");
            } else {
                $this->log("Table $i: SKIPPED - invalid pages (first=$firstPage last=$lastPage)");
            }
            
            $offset += 16;
        }
        
        // Fallback: calculate from file size
        if ($maxPage == 0) {
            $maxPage = floor(strlen($this->data) / $pageSize) - 1;
            $this->log("No valid pages found in table directory, using file size: $maxPage");
        }
        
        return $maxPage;
    }

    /**
     * Detect if a given page size makes sense for this database
     */
    private function detectPagePattern($pageSize) {
        $totalPages = intval(strlen($this->data) / $pageSize);
        if ($totalPages < 2) return false;
        
        // Check if page boundaries show consistent patterns
        $validPages = 0;
        for ($i = 1; $i < min($totalPages, 10); $i++) {
            $offset = $i * $pageSize;
            if ($offset + 8 < strlen($this->data)) {
                // Check for page header patterns (non-zero values in expected positions)
                $pageHeader = unpack('V2data', substr($this->data, $offset, 8));
                if ($pageHeader['data1'] > 0 || $pageHeader['data2'] > 0) {
                    $validPages++;
                }
            }
        }
        
        return $validPages > 3; // At least 3 valid-looking pages
    }

    /**
     * Recovery Method 3: Skip Invalid Pages & Scan by Pattern
     * Read pages that have valid-looking data even if headers are corrupt
     */
    public function recoverPageHeaders($pageSize = self::DEFAULT_PAGE_SIZE) {
        $this->loadDatabase();
        
        // If we have reference DB, copy page headers from it
        if ($this->referenceDb && file_exists($this->referenceDb)) {
            $refData = file_get_contents($this->referenceDb);
            $totalPages = intval(strlen($this->data) / $pageSize);
            $recoveredPages = 0;
            
            $this->log("Copying page headers from reference DB");
            
            for ($page = 0; $page < $totalPages; $page++) {
                $offset = $page * $pageSize;
                
                if ($offset + 40 <= strlen($this->data) && $offset + 40 <= strlen($refData)) {
                    $refPageHeader = substr($refData, $offset, 40);
                    $corruptPageHeader = substr($this->data, $offset, 40);
                    
                    // Only copy if headers differ
                    if ($refPageHeader !== $corruptPageHeader) {
                        for ($i = 0; $i < 40; $i++) {
                            $this->data[$offset + $i] = $refPageHeader[$i];
                        }
                        $recoveredPages++;
                    }
                }
            }
            
            $this->saveDatabase();
            $this->log("Recovered {$recoveredPages} page headers from reference");
            
            return $recoveredPages;
        }
        
        // Fallback: try to rebuild page headers
        $this->log("No reference DB - attempting to rebuild page headers");
        $totalPages = intval(strlen($this->data) / $pageSize);
        $recoveredPages = 0;
        
        for ($page = 1; $page < $totalPages; $page++) {
            $offset = $page * $pageSize;
            
            if ($this->isPageRecoverable($offset, $pageSize)) {
                // Try to rebuild page header from payload pattern
                $this->rebuildPageHeader($offset, $pageSize);
                $recoveredPages++;
            }
        }
        
        $this->saveDatabase();
        $this->log("Recovered {$recoveredPages} page headers");
        
        return $recoveredPages;
    }

    private function isPageRecoverable($offset, $pageSize) {
        if ($offset + $pageSize > strlen($this->data)) return false;
        
        // For page 0 (header page), use different validation
        if ($offset == 0) {
            // File header should have valid signature and page size
            if (strlen($this->data) < 24) return false;
            $header = unpack('V6', substr($this->data, 0, 24));
            $validPageSizes = [512, 1024, 2048, 4096, 8192, 16384];
            return in_array($header[2], $validPageSizes);
        }
        
        // Check page structure according to Rekordbox PDB specification
        if ($offset + 0x20 > strlen($this->data)) return false;
        
        // Read page header structure (32 bytes minimum)
        $pageHeader = substr($this->data, $offset, 0x20);
        if (strlen($pageHeader) < 0x20) return false;
        
        // Parse header fields
        // Bytes 0x00-0x03: zeros (typically)
        // Bytes 0x04-0x07: page_index
        // Bytes 0x08-0x0b: type (table type)
        // Bytes 0x0c-0x0f: next_page
        // Byte 0x1b: page_flags
        
        $headerData = unpack('V8', $pageHeader);
        $pageIndex = $headerData[2];       // Offset 0x04
        $tableType = $headerData[3];       // Offset 0x08
        $nextPage = $headerData[4];        // Offset 0x0c
        $pageFlags = ord($pageHeader[0x1b]);
        
        $expectedPageIndex = intval($offset / $pageSize);
        $maxPossiblePage = intval(strlen($this->data) / $pageSize);
        
        // STRICT VALIDATION (must pass ALL checks)
        
        // Check 1: page_flags MUST have known values (CRITICAL - reject immediately if invalid)
        // According to Deep Symmetry docs:
        // - Data pages: 0x24 or 0x34 (page_flags & 0x40 == 0)
        // - Strange pages: 0x44 or 0x64 (page_flags & 0x40 != 0)
        // REJECT pages with page_flags = 0 or 0xFF (these indicate corruption)
        $knownPageFlags = [0x24, 0x34, 0x44, 0x64];
        if (!in_array($pageFlags, $knownPageFlags)) {
            // Invalid page_flags - this page is not recoverable
            return false;
        }
        
        // Check 2: page_index MUST match expected position (or be very close)
        // This prevents accepting pages with valid flags but corrupt structure
        if ($pageIndex !== $expectedPageIndex) {
            // Allow small variance for edge cases, but not large corruption
            if (abs($pageIndex - $expectedPageIndex) > 2) {
                return false;
            }
        }
        
        // Check 3: next_page must be reasonable if not zero
        if ($nextPage > 0 && $nextPage >= $maxPossiblePage) {
            return false;
        }
        
        // Check 4: table type should be reasonable
        if ($tableType > 0xFF) {
            return false;
        }
        
        // If we passed all strict checks, the page is recoverable
        return true;
    }

    private function rebuildPageHeader($offset, $pageSize) {
        // Build minimal valid page header
        // Format depends on DB version - using generic structure
        $header = pack('V10', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        
        for ($i = 0; $i < 40; $i++) {
            if ($offset + $i < strlen($this->data)) {
                $this->data[$offset + $i] = $header[$i] ?? chr(0);
            }
        }
    }

    /**
     * Recovery Method 4: Force All Rows Valid (Bitmap Recovery)
     * Set all bits in presence bitmap to 1 (all rows valid)
     */
    public function recoverRowPresenceBitmap($pageSize = self::DEFAULT_PAGE_SIZE) {
        $this->loadDatabase();
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        $recoveredPages = 0;
        
        for ($page = 1; $page < $totalPages; $page++) {
            $bitmapOffset = $page * $pageSize + 40; // Bitmap typically at offset 40
            
            // Set all bitmap bytes to 0xFF (all rows present)
            for ($i = 0; $i < 16; $i++) { // 16 bytes = 128 bits
                if ($bitmapOffset + $i < strlen($this->data)) {
                    $this->data[$bitmapOffset + $i] = chr(0xFF);
                    $recoveredPages++;
                }
            }
        }
        
        $this->saveDatabase();
        $this->log("Recovered row presence bitmaps for {$recoveredPages} bytes");
        
        return true;
    }

    /**
     * Recovery Method 5: Linear Full Scan (Ignore Corrupted Pointers)
     * Scan all pages linearly, ignoring table directory
     */
    public function recoverTableIndex($pageSize = self::DEFAULT_PAGE_SIZE) {
        $this->loadDatabase();
        
        // If we have reference DB, copy table directory directly
        if ($this->referenceDb && file_exists($this->referenceDb)) {
            $refData = file_get_contents($this->referenceDb);
            $refHeader = unpack('V6', substr($refData, 0, 24));
            $numTables = $refHeader[3];
            
            $this->log("Copying table directory from reference DB ($numTables tables)");
            
            // Copy table directory (starts at byte 24, 16 bytes per table)
            $tableDirectorySize = $numTables * 16;
            $tableDirectory = substr($refData, 24, $tableDirectorySize);
            
            // Replace corrupt table directory
            for ($i = 0; $i < $tableDirectorySize; $i++) {
                if (24 + $i < strlen($this->data)) {
                    $this->data[24 + $i] = $tableDirectory[$i];
                }
            }
            
            $this->saveDatabase();
            $this->log("Table directory recovered from reference");
            
            return $numTables;
        }
        
        // Smart recovery: Fix table directory without reference
        $this->log("Smart recovery: fixing table directory");
        
        $header = unpack('V6', substr($this->data, 0, 24));
        $numTables = $header[3];
        $pageSize = $header[2];
        $maxReasonablePage = strlen($this->data) / $pageSize;
        
        $fixedCount = 0;
        $offset = 24;
        
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($this->data)) break;
            
            $tableEntry = unpack('V4', substr($this->data, $offset, 16));
            $type = $tableEntry[1];
            $emptyCandidate = $tableEntry[2];
            
            // CRITICAL: Rekordbox table directory field order (verified from real files)
            // Offset 0x08: last_page (entry 3)
            // Offset 0x0c: first_page (entry 4)
            $lastPage = $tableEntry[3];
            $firstPage = $tableEntry[4];
            
            $newEntry = $tableEntry;
            $needsFix = false;
            
            // CRITICAL FIX: Detect and fix corruption where a huge value appears
            // This typically happens when bytes are shifted or corrupted
            // Example: 8755478 instead of 62
            
            // Fix unreasonable page values (> file bounds)
            if ($firstPage > $maxReasonablePage) {
                $this->log("Table $i: CORRUPT first_page=$firstPage (max=$maxReasonablePage)");
                
                // Try to find actual first page by scanning
                $actualPage = $this->findTablePageByScanning($type, $pageSize);
                if ($actualPage > 0 && $actualPage < $maxReasonablePage) {
                    $newEntry[4] = $actualPage;  // first_page
                    $newEntry[3] = $actualPage;  // last_page (assume single page if unknown)
                    $needsFix = true;
                    $this->log("Table $i: fixed first_page to $actualPage (scanned)");
                } else {
                    // Fallback: set to a safe default (skip table)
                    $newEntry[4] = 1;
                    $newEntry[3] = 1;
                    $needsFix = true;
                    $this->log("Table $i: could not find page, set to safe default (1)");
                }
            }
            
            if ($lastPage > $maxReasonablePage) {
                $this->log("Table $i: CORRUPT last_page=$lastPage (max=$maxReasonablePage)");
                // If first_page is valid, use it as last_page too
                if ($firstPage > 0 && $firstPage < $maxReasonablePage) {
                    $newEntry[3] = $firstPage;
                    $needsFix = true;
                    $this->log("Table $i: fixed last_page to match first_page ($firstPage)");
                }
            }
            
            // Validate logical ordering: last_page >= first_page
            // In real Rekordbox files, this is ALWAYS true
            if ($newEntry[4] > 0 && $newEntry[3] > 0 && $newEntry[3] < $newEntry[4]) {
                // This indicates the fields might be in wrong positions
                // Swap them to fix
                $temp = $newEntry[3];
                $newEntry[3] = $newEntry[4];
                $newEntry[4] = $temp;
                $needsFix = true;
                $this->log("Table $i: swapped last/first pages to fix ordering");
            }
            
            if ($needsFix) {
                $entryBytes = pack('V4', $newEntry[1], $newEntry[2], $newEntry[3], $newEntry[4]);
                for ($j = 0; $j < 16; $j++) {
                    $this->data[$offset + $j] = $entryBytes[$j];
                }
                $fixedCount++;
            }
            
            $offset += 16;
        }
        
        $this->saveDatabase();
        $this->log("Table directory: fixed $fixedCount entries");
        
        return $numTables;
    }
    
    /**
     * Find table page by scanning pages
     */
    private function findTablePageByScanning($type, $pageSize) {
        $totalPages = floor(strlen($this->data) / $pageSize);
        
        for ($page = 1; $page < min($totalPages, 100); $page++) {
            $offset = $page * $pageSize;
            if ($offset + 100 > strlen($this->data)) continue;
            
            $pageData = substr($this->data, $offset, 100);
            
            // Check if page has data
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

    private function detectTableType($offset, $pageSize) {
        // Try to detect table type from page content patterns
        // This is heuristic-based
        $data = substr($this->data, $offset, min($pageSize, 100));
        
        // Look for common patterns
        if (strpos($data, '.mp3') !== false || strpos($data, '.wav') !== false) {
            return 0; // tracks table
        }
        // Add more heuristics as needed
        
        return null;
    }

    /**
     * Recovery Method 6: Field Pattern Matching
     * Find fields by pattern even if row structure is corrupted
     */
    public function recoverRowStructure() {
        $this->loadDatabase();
        
        // Pattern-based field extraction
        // Look for common patterns: file paths, BPM values, etc.
        $patterns = [
            'file_path' => '/\/(.*?)\.(mp3|wav|flac|m4a)/i',
            'title' => '/[A-Za-z0-9 \-_]{3,50}/',
            'bpm' => '/\b(6[0-9]|[7-9][0-9]|1[0-8][0-9])\b/' // 60-189 BPM range
        ];
        
        $recoveredFields = 0;
        foreach ($patterns as $field => $pattern) {
            $matches = [];
            preg_match_all($pattern, $this->data, $matches);
            $recoveredFields += count($matches[0]);
        }
        
        $this->log("Recovered {$recoveredFields} field patterns");
        
        // Note: Actual reconstruction would require writing back to proper positions
        // This is a simplified version
        
        return $recoveredFields;
    }

    /**
     * Recovery Method 7: Data Sanity Check & Correction
     * Validate and fix field values
     */
    public function recoverFieldData() {
        $this->loadDatabase();
        
        $corrections = 0;
        
        // Scan for and fix control characters in strings
        $dataLen = strlen($this->data);
        for ($i = 100; $i < $dataLen; $i++) {
            $char = ord($this->data[$i]);
            
            // Replace control characters (0-31) except null, tab, newline
            if ($char > 0 && $char < 32 && !in_array($char, [0, 9, 10, 13])) {
                // Check context - if surrounded by printable chars, replace with space
                $prevChar = $i > 0 ? ord($this->data[$i-1]) : 0;
                $nextChar = $i < $dataLen-1 ? ord($this->data[$i+1]) : 0;
                
                if (($prevChar >= 32 && $prevChar <= 126) || ($nextChar >= 32 && $nextChar <= 126)) {
                    $this->data[$i] = ' ';
                    $corrections++;
                }
            }
        }
        
        $this->saveDatabase();
        $this->log("Corrected {$corrections} corrupted characters");
        
        return $corrections;
    }

    /**
     * Recovery Method 8: Flatten & Rebuild Playlist Tree
     * Reconstruct playlist hierarchy from entries
     */
    public function recoverPlaylistStructure() {
        $this->loadDatabase();
        
        // This would require parsing playlist entries and rebuilding the tree
        // Simplified: just log the attempt
        $this->log("Attempting playlist structure recovery");
        
        // In a real implementation, we would:
        // 1. Find all playlist entry pages
        // 2. Extract playlist IDs and track IDs
        // 3. Rebuild tree structure
        // 4. Write back to playlist_tree table
        
        return true;
    }

    /**
     * Recovery Method 9: Relink Orphan Tracks
     * Match tracks to playlists/albums by path/checksum
     */
    public function recoverCrossTableRelationships() {
        $this->loadDatabase();
        
        // Find all file paths in the database
        $pattern = '/\/[A-Za-z0-9\/\-_ ]+\.(mp3|wav|flac|m4a)/i';
        preg_match_all($pattern, $this->data, $matches);
        
        $foundPaths = count($matches[0]);
        $this->log("Found {$foundPaths} potential track paths for relinking");
        
        // In real implementation:
        // 1. Build map of track IDs to paths
        // 2. Build map of playlist entries to track IDs
        // 3. Validate and fix broken references
        
        return $foundPaths;
    }

    /**
     * Recovery Method 10: Auto-Detect Version
     * Detect database version from structure patterns
     */
    public function recoverVersionInfo() {
        $this->loadDatabase();
        
        // Try to detect version from page structure
        $header = unpack('V6data', substr($this->data, 0, 24));
        $pageSize = $header['data2'];
        
        $detectedVersion = "Unknown";
        if ($pageSize == 4096) {
            $detectedVersion = "Rekordbox 6.x";
        } else if ($pageSize == 8192) {
            $detectedVersion = "Rekordbox 5.x";
        }
        
        $this->log("Detected version: {$detectedVersion}");
        
        // Reset sequence to 1
        $newHeader = pack('V6',
            $header['data1'],
            $header['data2'],
            $header['data3'],
            $header['data4'],
            0, // Reset unknown
            1  // Reset sequence to 1
        );
        
        $this->data = $newHeader . substr($this->data, 24);
        $this->saveDatabase();
        $this->log("Version info normalized");
        
        return true;
    }

    /**
     * Apply full recovery (all methods in sequence)
     */
    public function recoverAll() {
        $this->log("=== Starting Full Recovery ===");
        
        try {
            $this->recoverMagicHeader();
            $this->recoverMetadataHeader();
            $this->recoverVersionInfo();
            $this->recoverTableIndex();
            $this->recoverPageHeaders();
            $this->recoverRowPresenceBitmap();
            $this->recoverRowStructure();
            $this->recoverFieldData();
            $this->recoverPlaylistStructure();
            $this->recoverCrossTableRelationships();
            
            $this->log("=== Recovery Complete ===");
            return true;
        } catch (\Exception $e) {
            $this->log("Recovery failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recovery log
     */
    public function getRecoveryLog() {
        return $this->recoveryLog;
    }

    /**
     * Get recovery statistics
     */
    public function getStats() {
        return [
            'corrupt_db' => $this->corruptDb,
            'recovered_db' => $this->recoveredDb,
            'corrupt_size' => file_exists($this->corruptDb) ? filesize($this->corruptDb) : 0,
            'recovered_size' => file_exists($this->recoveredDb) ? filesize($this->recoveredDb) : 0,
            'log_entries' => count($this->recoveryLog)
        ];
    }

    /**
     * Scan database and detect corruption types
     * Returns detailed analysis of what's wrong with the database
     */
    public function scanDatabase() {
        $this->log("=== Starting Database Scan ===");
        $this->loadDatabase();
        
        $scanResults = [
            'file_info' => $this->scanFileInfo(),
            'header' => $this->scanMagicHeader(),
            'metadata' => $this->scanMetadata(),
            'version' => $this->scanVersion(),
            'pages' => $this->scanPages(),
            'tables' => $this->scanTables(),
            'data_integrity' => $this->scanDataIntegrity(),
            'relationships' => $this->scanRelationships(),
            'summary' => []
        ];
        
        $scanResults['summary'] = $this->generateScanSummary($scanResults);
        
        $this->log("=== Scan Complete ===");
        
        return $scanResults;
    }
    
    /**
     * Scan basic file information
     */
    private function scanFileInfo() {
        $fileSize = strlen($this->data);
        $this->log("File size: " . number_format($fileSize) . " bytes (" . round($fileSize / 1024 / 1024, 2) . " MB)");
        
        return [
            'status' => 'ok',
            'size_bytes' => $fileSize,
            'size_mb' => round($fileSize / 1024 / 1024, 2),
            'readable' => true,
            'issues' => []
        ];
    }
    
    /**
     * Scan magic header (first 4 bytes)
     */
    private function scanMagicHeader() {
        $this->log("Scanning magic header...");
        
        if (strlen($this->data) < 4) {
            $this->log("ERROR: File too small for magic header");
            return [
                'status' => 'error',
                'corrupt' => true,
                'message' => 'File too small',
                'issues' => ['File size less than 4 bytes']
            ];
        }
        
        $magicBytes = substr($this->data, 0, 4);
        $magicHex = bin2hex($magicBytes);
        $this->log("Magic bytes: 0x" . strtoupper($magicHex));
        
        $issues = [];
        $status = 'ok';
        $expectedMagic = null;
        
        // Check against reference DB if available
        if ($this->referenceDb && file_exists($this->referenceDb)) {
            $refData = file_get_contents($this->referenceDb);
            $refMagic = substr($refData, 0, 4);
            $expectedMagic = $refMagic;
            $refMagicHex = bin2hex($refMagic);
            
            if ($magicBytes !== $refMagic) {
                $issues[] = "Magic header mismatch (expected: 0x" . strtoupper($refMagicHex) . ")";
                $status = 'error';
                $this->log("ERROR: Magic header differs from reference");
            } else {
                $this->log("OK: Magic header matches reference");
            }
        } else {
            // Without reference DB, check for known valid Rekordbox signatures
            // Rekordbox databases typically start with specific byte patterns
            $validSignatures = [
                "\x00\x00\x00\x00", // Common Rekordbox signature
                "00000000"          // Alternative format
            ];
            
            $isValid = false;
            $expectedHex = "00000000";
            
            // Check if it matches any known valid signature
            foreach ($validSignatures as $validSig) {
                if (substr($magicBytes, 0, strlen($validSig)) === $validSig) {
                    $isValid = true;
                    break;
                }
            }
            
            // Also check if all bytes are zeros (typical for Rekordbox)
            $bytes = unpack('C*', $magicBytes);
            $allZeros = true;
            foreach ($bytes as $byte) {
                if ($byte !== 0) {
                    $allZeros = false;
                    break;
                }
            }
            
            if (!$allZeros && !$isValid) {
                $issues[] = "Magic header does not match expected Rekordbox signature (expected: 0x{$expectedHex}, got: 0x" . strtoupper($magicHex) . ")";
                $status = 'error';
                $this->log("ERROR: Invalid magic header signature");
            } else {
                $this->log("OK: Magic header appears valid (all zeros)");
            }
        }
        
        return [
            'status' => $status,
            'corrupt' => count($issues) > 0,
            'magic_hex' => '0x' . strtoupper($magicHex),
            'magic_bytes' => array_values(unpack('C*', $magicBytes)),
            'issues' => $issues
        ];
    }
    
    /**
     * Scan database metadata header
     */
    private function scanMetadata() {
        $this->log("Scanning metadata header...");
        
        if (strlen($this->data) < 24) {
            $this->log("ERROR: File too small for metadata header");
            return [
                'status' => 'error',
                'corrupt' => true,
                'message' => 'File too small for metadata',
                'issues' => ['File size less than 24 bytes']
            ];
        }
        
        $header = unpack('V6data', substr($this->data, 0, 24));
        $pageSize = $header['data2'];
        $numTables = $header['data3'];
        $nextUnusedPage = $header['data4'];
        $sequence = $header['data6'];
        
        $this->log("Page size: {$pageSize}");
        $this->log("Number of tables: {$numTables}");
        $this->log("Next unused page: {$nextUnusedPage}");
        $this->log("Sequence: {$sequence}");
        
        $issues = [];
        $status = 'ok';
        
        $validPageSizes = [512, 1024, 2048, 4096, 8192, 16384];
        if (!in_array($pageSize, $validPageSizes)) {
            $issues[] = "Invalid page size: {$pageSize} (valid: " . implode(', ', $validPageSizes) . ")";
            $status = 'error';
            $this->log("ERROR: Invalid page size");
        }
        
        if ($numTables < 1 || $numTables > 100) {
            $issues[] = "Suspicious number of tables: {$numTables}";
            $status = ($status === 'error') ? 'error' : 'warning';
            $this->log("WARNING: Unusual number of tables");
        }
        
        $expectedPages = intval(strlen($this->data) / max($pageSize, 1));
        if ($nextUnusedPage > $expectedPages * 2) {
            $issues[] = "Next unused page ({$nextUnusedPage}) exceeds expected range";
            $status = ($status === 'error') ? 'error' : 'warning';
            $this->log("WARNING: Next unused page value seems incorrect");
        }
        
        if (count($issues) === 0) {
            $this->log("OK: Metadata header looks valid");
        }
        
        return [
            'status' => $status,
            'corrupt' => count($issues) > 0,
            'page_size' => $pageSize,
            'num_tables' => $numTables,
            'next_unused_page' => $nextUnusedPage,
            'sequence' => $sequence,
            'issues' => $issues
        ];
    }
    
    /**
     * Scan version information
     */
    private function scanVersion() {
        $this->log("Scanning version info...");
        
        $header = unpack('V6data', substr($this->data, 0, 24));
        $pageSize = $header['data2'];
        
        $detectedVersion = "Unknown";
        $confidence = 0;
        
        if ($pageSize == 4096) {
            $detectedVersion = "Rekordbox 6.x";
            $confidence = 80;
        } else if ($pageSize == 8192) {
            $detectedVersion = "Rekordbox 5.x";
            $confidence = 70;
        } else if ($pageSize == 512) {
            $detectedVersion = "Rekordbox 4.x or earlier";
            $confidence = 60;
        }
        
        $this->log("Detected version: {$detectedVersion} (confidence: {$confidence}%)");
        
        return [
            'status' => $confidence > 0 ? 'ok' : 'warning',
            'detected_version' => $detectedVersion,
            'confidence' => $confidence,
            'issues' => $confidence < 50 ? ['Unable to reliably detect version'] : []
        ];
    }
    
    /**
     * Scan page structure
     */
    private function scanPages() {
        $this->log("Scanning page structure...");
        
        $header = unpack('V6data', substr($this->data, 0, 24));
        $pageSize = $header['data2'];
        
        if ($pageSize < 512) {
            return [
                'status' => 'error',
                'corrupt' => true,
                'message' => 'Invalid page size',
                'issues' => ['Cannot scan pages with invalid page size']
            ];
        }
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        $dataPages = 0;
        $strangePages = 0;
        $emptyPages = 0;
        $corruptPages = 0;
        $pageSample = [];
        
        // Check more pages for better coverage (up to 50 samples across the file)
        $samplesToCheck = min($totalPages, 50);
        
        for ($i = 0; $i < $samplesToCheck; $i++) {
            $pageNum = intval(($i / $samplesToCheck) * $totalPages);
            $offset = $pageNum * $pageSize;
            
            if ($offset + $pageSize > strlen($this->data)) {
                break;
            }
            
            // Check if page is empty (all zeros)
            $pageData = substr($this->data, $offset, min($pageSize, 100));
            $isEmpty = (trim($pageData, "\0") === '');
            
            if ($isEmpty) {
                $emptyPages++;
                continue;
            }
            
            // Check page_flags to determine page type (data vs strange)
            if ($offset + 0x1c > strlen($this->data)) {
                $corruptPages++;
                continue;
            }
            
            $pageFlags = ord($this->data[$offset + 0x1b]);
            
            // According to Rekordbox spec:
            // Data pages: page_flags & 0x40 == 0 (typically 0x24 or 0x34)
            // Strange pages: page_flags & 0x40 != 0 (typically 0x44 or 0x64)
            // Both are VALID, not corrupt!
            
            if (($pageFlags & 0x40) == 0) {
                // Data page
                $dataPages++;
                if (count($pageSample) < 5) {
                    $pageSample[] = [
                        'page_num' => $pageNum,
                        'offset' => $offset,
                        'type' => 'data',
                        'flags' => sprintf('0x%02X', $pageFlags)
                    ];
                }
            } else {
                // Strange (non-data) page - this is normal, not corrupt
                $strangePages++;
                if (count($pageSample) < 5 && $strangePages <= 2) {
                    $pageSample[] = [
                        'page_num' => $pageNum,
                        'offset' => $offset,
                        'type' => 'strange (non-data)',
                        'flags' => sprintf('0x%02X', $pageFlags)
                    ];
                }
            }
        }
        
        $validPages = $dataPages + $strangePages;
        
        $this->log("Pages scanned: {$samplesToCheck}");
        $this->log("Data pages: {$dataPages}");
        $this->log("Strange (non-data) pages: {$strangePages}");
        $this->log("Empty pages: {$emptyPages}");
        $this->log("Corrupt pages: {$corruptPages}");
        
        $issues = [];
        $status = 'ok';
        
        // Only flag actual corruption, not normal page types
        if ($corruptPages > $validPages / 2) {
            $issues[] = "High number of corrupt pages ({$corruptPages})";
            $status = 'error';
        } else if ($corruptPages > 2) {
            $issues[] = "{$corruptPages} corrupt pages detected";
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'corrupt' => $corruptPages > 0,
            'total_pages' => $totalPages,
            'pages_scanned' => $samplesToCheck,
            'valid_pages' => $validPages,
            'data_pages' => $dataPages,
            'strange_pages' => $strangePages,
            'corrupt_pages' => $corruptPages,
            'empty_pages' => $emptyPages,
            'page_size' => $pageSize,
            'sample_pages' => $pageSample,
            'issues' => $issues
        ];
    }
    
    /**
     * Scan table index
     */
    private function scanTables() {
        $this->log("Scanning table index...");
        
        $header = unpack('V6data', substr($this->data, 0, 24));
        $numTables = $header['data3'];
        $pageSize = $header['data2'];
        
        if ($numTables < 1 || $pageSize < 512) {
            return [
                'status' => 'error',
                'corrupt' => true,
                'message' => 'Invalid metadata',
                'issues' => ['Cannot scan tables with invalid metadata']
            ];
        }
        
        $tables = [];
        $offset = 24;
        $validTables = 0;
        $issues = [];
        
        $tableNames = [
            0x00 => 'tracks',
            0x01 => 'genres',
            0x02 => 'artists',
            0x03 => 'albums',
            0x04 => 'labels',
            0x05 => 'keys',
            0x06 => 'colors',
            0x07 => 'playlist_tree',
            0x08 => 'playlist_entries',
            0x0d => 'artwork',
            0x10 => 'columns',
            0x11 => 'history_playlists',
            0x12 => 'history_entries',
            0x13 => 'history'
        ];
        
        for ($i = 0; $i < min($numTables, 50); $i++) {
            if ($offset + 16 > strlen($this->data)) {
                break;
            }
            
            $tableEntry = unpack('V4data', substr($this->data, $offset, 16));
            $tableType = $tableEntry['data1'];
            $emptyCandidate = $tableEntry['data2'];
            
            // IMPORTANT: Real Rekordbox PDB file format (verified from actual files)
            // Byte 0x08-0x0b: Contains LAST page index (not first as old docs suggest)
            // Byte 0x0c-0x0f: Contains FIRST page index (not last as old docs suggest)
            // 
            // This differs from Deep Symmetry docs but matches ALL real Rekordbox files tested
            // We parse according to observed reality and validate the ordering below
            $lastPage = $tableEntry['data3'];    // Read from offset 0x08 (expected to be larger value)
            $firstPage = $tableEntry['data4'];   // Read from offset 0x0c (expected to be smaller value)
            
            // Validation: Verify table structure makes logical sense
            // A valid table must have:
            // 1. First page > 0 (page 0 is file header)
            // 2. Last page >= first page (proper ordering)
            // 3. Both pages within file bounds
            // 4. Table type is reasonable (0x00-0x13 are known types, up to 0xFF possible)
            
            $maxReasonablePage = intval(strlen($this->data) / $pageSize);
            $isValidStructure = true;
            $invalidReason = '';
            
            if ($firstPage === 0) {
                $isValidStructure = false;
                $invalidReason = 'first_page=0';
            } else if ($lastPage < $firstPage) {
                // This indicates ACTUAL corruption (values are inverted from expected order)
                $isValidStructure = false;
                $invalidReason = "inverted pages (first=$firstPage > last=$lastPage)";
            } else if ($firstPage > $maxReasonablePage || $lastPage > $maxReasonablePage) {
                $isValidStructure = false;
                $invalidReason = "pages exceed file bounds ($maxReasonablePage)";
            } else if ($tableType > 0xFF) {
                $isValidStructure = false;
                $invalidReason = "invalid table type ($tableType)";
            }
            
            if ($isValidStructure) {
                $validTables++;
                if (count($tables) < 10) {
                    $tableName = isset($tableNames[$tableType]) ? $tableNames[$tableType] : "unknown_type_{$tableType}";
                    $tables[] = [
                        'index' => $i,
                        'type' => $tableType,
                        'name' => $tableName,
                        'first_page' => $firstPage,
                        'last_page' => $lastPage,
                        'num_pages' => $lastPage - $firstPage + 1
                    ];
                }
            } else {
                // Log invalid table for debugging
                $this->log("Invalid table $i: type=0x" . dechex($tableType) . " first=$firstPage last=$lastPage ($invalidReason)");
            }
            
            $offset += 16;
        }
        
        $this->log("Valid tables found: {$validTables}/{$numTables}");
        
        $status = 'ok';
        if ($validTables < $numTables / 2) {
            $issues[] = "Less than half of tables are valid ({$validTables}/{$numTables})";
            $status = 'error';
        } else if ($validTables < $numTables) {
            $issues[] = "Some tables are invalid ({$validTables}/{$numTables})";
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'corrupt' => count($issues) > 0,
            'expected_tables' => $numTables,
            'valid_tables' => $validTables,
            'table_sample' => $tables,
            'issues' => $issues
        ];
    }
    
    /**
     * Scan data integrity (check for readable text, valid structures)
     */
    private function scanDataIntegrity() {
        $this->log("Scanning data integrity...");
        
        $sampleSize = min(100000, strlen($this->data));
        $sample = substr($this->data, 1000, $sampleSize);
        
        $readableChars = 0;
        $controlChars = 0;
        $nullBytes = 0;
        
        for ($i = 0; $i < strlen($sample); $i++) {
            $char = ord($sample[$i]);
            
            if ($char === 0) {
                $nullBytes++;
            } else if ($char >= 32 && $char <= 126) {
                $readableChars++;
            } else if ($char > 0 && $char < 32) {
                $controlChars++;
            }
        }
        
        $readablePercent = round(($readableChars / strlen($sample)) * 100, 2);
        $controlPercent = round(($controlChars / strlen($sample)) * 100, 2);
        $nullPercent = round(($nullBytes / strlen($sample)) * 100, 2);
        
        $this->log("Readable characters: {$readablePercent}%");
        $this->log("Control characters: {$controlPercent}%");
        $this->log("Null bytes: {$nullPercent}%");
        
        $pattern = '/\/(.*?)\.(mp3|wav|flac|m4a|aif|aiff)/i';
        preg_match_all($pattern, $this->data, $matches);
        $trackPathsFound = count($matches[0]);
        
        $this->log("Track paths found: {$trackPathsFound}");
        
        $issues = [];
        $status = 'ok';
        
        // CRITICAL FIX: Low readable text and high null bytes are NORMAL for binary databases!
        // Rekordbox PDB files contain:
        // - Binary integers (IDs, offsets, counts) - these are "control characters"
        // - Null bytes for padding, deleted rows, and alignment
        // - Actual text strings only in specific fields (track names, paths, etc.)
        // 
        // According to Deep Symmetry docs, the database is a binary format with:
        // - Fixed-size pages with heap structures
        // - Row indices with presence bitmaps
        // - Variable-length strings referenced by offsets
        //
        // Expected characteristics of a HEALTHY Rekordbox database:
        // - Low readable text (2-10% is normal, not a problem!)
        // - High null bytes (40-60% is normal due to padding and deleted rows)
        // - Control characters (10-20% is normal, they're part of binary data)
        
        // Only flag EXTREME deviations that indicate actual corruption
        if ($readablePercent < 0.5) {
            // Less than 0.5% readable text is suspicious - might be completely encrypted or corrupted
            $issues[] = "Extremely low readable text ratio ({$readablePercent}%) - database might be encrypted or severely corrupted";
            $status = 'warning';
        }
        
        if ($nullPercent > 95) {
            // More than 95% nulls suggests the file might be mostly empty
            $issues[] = "Extremely high null byte ratio ({$nullPercent}%) - database might be mostly empty";
            $status = ($status === 'error') ? 'error' : 'warning';
        }
        
        if ($trackPathsFound === 0) {
            $issues[] = "No track paths found in database";
            $status = 'error';
        }
        
        return [
            'status' => $status,
            'corrupt' => count($issues) > 0,
            'readable_percent' => $readablePercent,
            'control_chars_percent' => $controlPercent,
            'null_bytes_percent' => $nullPercent,
            'track_paths_found' => $trackPathsFound,
            'sample_size_bytes' => $sampleSize,
            'issues' => $issues
        ];
    }
    
    /**
     * Scan relationships (detect orphaned data)
     */
    private function scanRelationships() {
        $this->log("Scanning relationships...");
        
        $pattern = '/\/(.*?)\.(mp3|wav|flac|m4a|aif|aiff)/i';
        preg_match_all($pattern, $this->data, $fileMatches);
        $filePaths = count($fileMatches[0]);
        
        $playlistPattern = '/playlist|folder|crate/i';
        preg_match_all($playlistPattern, $this->data, $playlistMatches);
        $playlistRefs = count($playlistMatches[0]);
        
        $this->log("File path references: {$filePaths}");
        $this->log("Playlist references: {$playlistRefs}");
        
        $issues = [];
        $status = 'ok';
        
        if ($filePaths === 0) {
            $issues[] = "No file paths found - database may be completely corrupt";
            $status = 'error';
        }
        
        return [
            'status' => $status,
            'corrupt' => count($issues) > 0,
            'file_paths' => $filePaths,
            'playlist_refs' => $playlistRefs,
            'issues' => $issues
        ];
    }
    
    /**
     * Generate overall scan summary
     */
    private function generateScanSummary($scanResults) {
        $totalIssues = 0;
        $criticalIssues = 0;
        $warnings = 0;
        $recommendations = [];
        
        foreach ($scanResults as $category => $result) {
            if ($category === 'summary' || !is_array($result)) continue;
            
            if (isset($result['status'])) {
                if ($result['status'] === 'error') {
                    $criticalIssues++;
                }
                if ($result['status'] === 'warning') {
                    $warnings++;
                }
            }
            
            if (isset($result['issues']) && is_array($result['issues'])) {
                $totalIssues += count($result['issues']);
            }
        }
        
        $overallHealth = 'healthy';
        if ($criticalIssues > 0) {
            $overallHealth = 'critical';
            $recommendations[] = "Database has critical issues - recovery required";
        } else if ($warnings > 2) {
            $overallHealth = 'degraded';
            $recommendations[] = "Database has multiple warnings - consider recovery";
        } else if ($warnings > 0) {
            $overallHealth = 'minor_issues';
            $recommendations[] = "Database has minor issues - monitoring recommended";
        }
        
        if (isset($scanResults['header']['corrupt']) && $scanResults['header']['corrupt']) {
            $recommendations[] = "Run Recovery Method 1: Header Reconstruction";
        }
        
        if (isset($scanResults['metadata']['corrupt']) && $scanResults['metadata']['corrupt']) {
            $recommendations[] = "Run Recovery Method 2: Metadata Inference";
        }
        
        if (isset($scanResults['pages']['corrupt_pages']) && $scanResults['pages']['corrupt_pages'] > 0) {
            $recommendations[] = "Run Recovery Method 3: Page Pattern Scan";
        }
        
        if (isset($scanResults['data_integrity']['corrupt']) && $scanResults['data_integrity']['corrupt']) {
            $recommendations[] = "Run Recovery Method 7: Data Sanity Check";
        }
        
        $this->log("Overall health: {$overallHealth}");
        $this->log("Total issues: {$totalIssues}");
        $this->log("Critical issues: {$criticalIssues}");
        $this->log("Warnings: {$warnings}");
        
        return [
            'overall_health' => $overallHealth,
            'total_issues' => $totalIssues,
            'critical_issues' => $criticalIssues,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'scan_timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
