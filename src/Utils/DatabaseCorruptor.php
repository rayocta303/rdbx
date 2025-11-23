<?php

namespace RekordboxReader\Utils;

/**
 * DatabaseCorruptor - Simulate various database corruption scenarios for testing recovery
 * 
 * Supports 10 corruption scenarios:
 * 1. Magic Header Corruption
 * 2. Database Metadata Header Corruption
 * 3. Page Header Corruption
 * 4. Row Presence Bitmap Corruption
 * 5. Table Index/Page Reference Corruption
 * 6. Row Structure Corruption
 * 7. Field-Level Data Corruption
 * 8. Playlist Structure Corruption
 * 9. Cross-Table Relationship Corruption
 * 10. Internal Version Mismatch
 */
class DatabaseCorruptor {
    private $sourceDb;
    private $targetDb;
    private $data;
    private $logger;

    public function __construct($sourceDbPath, $targetDbPath, $logger = null) {
        $this->sourceDb = $sourceDbPath;
        $this->targetDb = $targetDbPath;
        $this->logger = $logger;
    }

    /**
     * Load database file into memory
     */
    private function loadDatabase() {
        if (!file_exists($this->sourceDb)) {
            throw new \Exception("Source database not found: {$this->sourceDb}");
        }
        $this->data = file_get_contents($this->sourceDb);
        if ($this->logger) {
            $this->logger->info("Loaded database: " . strlen($this->data) . " bytes");
        }
    }

    /**
     * Save corrupted database to target
     */
    private function saveDatabase() {
        $dir = dirname($this->targetDb);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->targetDb, $this->data);
        if ($this->logger) {
            $this->logger->info("Saved corrupted database to: {$this->targetDb}");
        }
    }

    /**
     * Scenario 1: Corrupt Magic Header / Signature
     * Replace first 4 bytes with invalid signature
     */
    public function corruptMagicHeader() {
        $this->loadDatabase();
        
        // Original signature is usually 4 bytes
        // Corrupt by replacing with random bytes
        $corruptBytes = pack('CCCC', rand(0, 255), rand(0, 255), rand(0, 255), rand(0, 255));
        $this->data = $corruptBytes . substr($this->data, 4);
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 1: Magic header corrupted");
        }
    }

    /**
     * Scenario 2: Corrupt Database Metadata Header
     * Corrupt page_size, num_tables, and other metadata
     */
    public function corruptMetadataHeader() {
        $this->loadDatabase();
        
        // Corrupt bytes 4-24 (page_size, num_tables, etc)
        for ($i = 4; $i < 24; $i++) {
            if (rand(0, 1)) {
                $this->data[$i] = chr(rand(0, 255));
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 2: Metadata header corrupted");
        }
    }

    /**
     * Scenario 3: Corrupt Page Headers
     * Corrupt headers of random pages
     */
    public function corruptPageHeaders($numPages = 5, $pageSize = 4096) {
        $this->loadDatabase();
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        $pagesToCorrupt = min($numPages, $totalPages - 1);
        
        for ($i = 0; $i < $pagesToCorrupt; $i++) {
            $pageIndex = rand(1, $totalPages - 1); // Don't corrupt page 0 (main header)
            $pageOffset = $pageIndex * $pageSize;
            
            // Corrupt first 40 bytes of page header
            for ($j = 0; $j < 40; $j++) {
                if ($pageOffset + $j < strlen($this->data)) {
                    $this->data[$pageOffset + $j] = chr(rand(0, 255));
                }
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 3: {$pagesToCorrupt} page headers corrupted");
        }
    }

    /**
     * Scenario 4: Corrupt Row Presence Bitmap
     * Flip bits in bitmap areas
     */
    public function corruptRowPresenceBitmap($pageSize = 4096) {
        $this->loadDatabase();
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        
        // Bitmap typically at offset 40-50 in each page
        for ($page = 1; $page < min($totalPages, 10); $page++) {
            $offset = $page * $pageSize + 40;
            
            // Corrupt 10 bytes of bitmap
            for ($i = 0; $i < 10; $i++) {
                if ($offset + $i < strlen($this->data)) {
                    $this->data[$offset + $i] = chr(rand(0, 255));
                }
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 4: Row presence bitmaps corrupted");
        }
    }

    /**
     * Scenario 5: Corrupt Table Index / Page References
     * Corrupt the table directory at header
     */
    public function corruptTableIndex() {
        $this->loadDatabase();
        
        // Table directory starts at offset 28, each table entry is 16 bytes
        $offset = 28;
        $numTables = 10; // Corrupt up to 10 table entries
        
        for ($i = 0; $i < $numTables; $i++) {
            $tableOffset = $offset + ($i * 16);
            if ($tableOffset + 16 > strlen($this->data)) break;
            
            // Randomly corrupt first_page or last_page pointers
            if (rand(0, 1)) {
                // Corrupt first_page (offset +8)
                $this->data[$tableOffset + 8] = chr(rand(0, 255));
                $this->data[$tableOffset + 9] = chr(rand(0, 255));
            }
            if (rand(0, 1)) {
                // Corrupt last_page (offset +12)
                $this->data[$tableOffset + 12] = chr(rand(0, 255));
                $this->data[$tableOffset + 13] = chr(rand(0, 255));
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 5: Table index/page references corrupted");
        }
    }

    /**
     * Scenario 6: Corrupt Row Structure
     * Corrupt row data alignment and offsets
     */
    public function corruptRowStructure($pageSize = 4096) {
        $this->loadDatabase();
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        
        // Corrupt row data in random pages
        for ($page = 1; $page < min($totalPages, 20); $page++) {
            $pageOffset = $page * $pageSize;
            $rowDataStart = $pageOffset + 100; // Skip page header
            
            // Corrupt random bytes in row data area
            for ($i = 0; $i < 50; $i++) {
                $pos = $rowDataStart + rand(0, 500);
                if ($pos < strlen($this->data)) {
                    $this->data[$pos] = chr(rand(0, 255));
                }
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 6: Row structures corrupted");
        }
    }

    /**
     * Scenario 7: Corrupt Field-Level Data
     * Corrupt string fields and numeric values
     */
    public function corruptFieldData($pageSize = 4096) {
        $this->loadDatabase();
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        
        // Find and corrupt strings (look for readable ASCII)
        for ($page = 1; $page < min($totalPages, 30); $page++) {
            $pageOffset = $page * $pageSize + 100;
            $endOffset = min($pageOffset + 3000, strlen($this->data));
            
            // Corrupt readable characters
            for ($i = $pageOffset; $i < $endOffset; $i++) {
                $char = ord($this->data[$i]);
                if ($char >= 32 && $char <= 126 && rand(0, 50) == 0) { // 2% chance to corrupt readable char
                    $this->data[$i] = chr(rand(0, 31)); // Replace with control char
                }
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 7: Field-level data corrupted");
        }
    }

    /**
     * Scenario 8: Corrupt Playlist Structure
     * Specifically target playlist tables
     */
    public function corruptPlaylistStructure($pageSize = 4096) {
        $this->loadDatabase();
        
        // Parse header to find playlist table locations
        $header = unpack('Vsignature/Vpage_size/Vnum_tables', substr($this->data, 0, 12));
        $numTables = $header['num_tables'];
        
        // Find playlist_tree (type 7) and playlist_entries (type 8)
        $offset = 28;
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($this->data)) break;
            
            $table = unpack('Vtype/Vempty/Vfirst_page/Vlast_page', substr($this->data, $offset, 16));
            
            if ($table['type'] == 7 || $table['type'] == 8) { // Playlist tables
                // Corrupt pages for these tables
                for ($page = $table['first_page']; $page <= min($table['last_page'], $table['first_page'] + 5); $page++) {
                    $pageOffset = $page * $pageSize + 50;
                    for ($j = 0; $j < 100; $j++) {
                        if ($pageOffset + $j < strlen($this->data)) {
                            $this->data[$pageOffset + $j] = chr(rand(0, 255));
                        }
                    }
                }
            }
            
            $offset += 16;
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 8: Playlist structure corrupted");
        }
    }

    /**
     * Scenario 9: Corrupt Cross-Table Relationships
     * Corrupt ID references between tables
     */
    public function corruptCrossTableRelationships($pageSize = 4096) {
        $this->loadDatabase();
        
        $totalPages = intval(strlen($this->data) / $pageSize);
        
        // Look for 4-byte integers that might be IDs and corrupt them
        for ($page = 1; $page < min($totalPages, 20); $page++) {
            $pageOffset = $page * $pageSize + 100;
            
            // Randomly corrupt potential ID fields (4-byte integers)
            for ($i = 0; $i < 20; $i++) {
                $pos = $pageOffset + (rand(0, 100) * 4);
                if ($pos + 4 < strlen($this->data)) {
                    // Replace with invalid ID (very high number)
                    $this->data[$pos] = chr(255);
                    $this->data[$pos + 1] = chr(255);
                    $this->data[$pos + 2] = chr(255);
                    $this->data[$pos + 3] = chr(127);
                }
            }
        }
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 9: Cross-table relationships corrupted");
        }
    }

    /**
     * Scenario 10: Create Version Mismatch
     * Modify version identifiers to simulate version drift
     */
    public function corruptVersionInfo() {
        $this->loadDatabase();
        
        // Corrupt sequence number and unknown fields in header
        $header = unpack('V6data', substr($this->data, 0, 24));
        $header['data5'] = rand(0, 9999999); // Corrupt sequence
        $header['data4'] = rand(0, 9999999); // Corrupt unknown
        
        $newHeader = pack('V6', 
            $header['data1'], 
            $header['data2'], 
            $header['data3'], 
            $header['data4'], 
            $header['data5'], 
            $header['data6']
        );
        
        $this->data = $newHeader . substr($this->data, 24);
        
        $this->saveDatabase();
        if ($this->logger) {
            $this->logger->info("Scenario 10: Version info corrupted");
        }
    }

    /**
     * Apply multiple corruption scenarios
     */
    public function corruptMultiple(array $scenarios) {
        $this->loadDatabase();
        
        foreach ($scenarios as $scenario) {
            switch ($scenario) {
                case 1: $this->corruptMagicHeader(); break;
                case 2: $this->corruptMetadataHeader(); break;
                case 3: $this->corruptPageHeaders(); break;
                case 4: $this->corruptRowPresenceBitmap(); break;
                case 5: $this->corruptTableIndex(); break;
                case 6: $this->corruptRowStructure(); break;
                case 7: $this->corruptFieldData(); break;
                case 8: $this->corruptPlaylistStructure(); break;
                case 9: $this->corruptCrossTableRelationships(); break;
                case 10: $this->corruptVersionInfo(); break;
            }
        }
    }

    /**
     * Get corruption info
     */
    public function getInfo() {
        return [
            'source' => $this->sourceDb,
            'target' => $this->targetDb,
            'source_size' => file_exists($this->sourceDb) ? filesize($this->sourceDb) : 0,
            'target_size' => file_exists($this->targetDb) ? filesize($this->targetDb) : 0
        ];
    }
}
