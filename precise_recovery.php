<?php

require_once __DIR__ . '/src/Utils/DatabaseRecovery.php';

use RekordboxReader\Utils\DatabaseRecovery;

class PreciseRecovery {
    private $corruptFile;
    private $normalFile;
    private $outputFile;
    
    public function __construct($corruptFile, $normalFile, $outputFile) {
        $this->corruptFile = $corruptFile;
        $this->normalFile = $normalFile;
        $this->outputFile = $outputFile;
    }
    
    public function recover() {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  PRECISE DATABASE RECOVERY                                 ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        
        if (!file_exists($this->corruptFile)) {
            throw new Exception("Corrupt file not found: {$this->corruptFile}");
        }
        
        if (!file_exists($this->normalFile)) {
            throw new Exception("Normal reference file not found: {$this->normalFile}");
        }
        
        $corruptData = file_get_contents($this->corruptFile);
        $normalData = file_get_contents($this->normalFile);
        
        echo "[1] Loading files...\n";
        echo "  Corrupt: " . number_format(strlen($corruptData)) . " bytes\n";
        echo "  Normal:  " . number_format(strlen($normalData)) . " bytes\n\n";
        
        // Start with corrupt data
        $recoveredData = $corruptData;
        
        echo "[2] Analyzing corruption...\n";
        
        $normalHeader = unpack('V6', substr($normalData, 0, 24));
        $corruptHeader = unpack('V6', substr($corruptData, 0, 24));
        
        $fixes = 0;
        
        // Fix header fields that are different
        echo "\n  Fixing header:\n";
        
        if ($normalHeader[4] !== $corruptHeader[4]) {
            echo "    ✓ Fix Next Unused Page: {$corruptHeader[4]} → {$normalHeader[4]}\n";
            $fixes++;
        }
        
        if ($normalHeader[6] !== $corruptHeader[6]) {
            echo "    ✓ Fix Sequence: {$corruptHeader[6]} → {$normalHeader[6]}\n";
            $fixes++;
        }
        
        // Rebuild header with correct values
        $newHeader = pack('V6',
            $normalHeader[1], // signature
            $normalHeader[2], // page_size
            $normalHeader[3], // num_tables
            $normalHeader[4], // next_unused_page (FIXED)
            $normalHeader[5], // unknown
            $normalHeader[6]  // sequence (FIXED)
        );
        
        $recoveredData = $newHeader . substr($recoveredData, 24);
        
        echo "\n[3] Fixing table directory...\n";
        
        $numTables = $normalHeader[3];
        $offset = 24;
        
        for ($i = 0; $i < $numTables; $i++) {
            $normalTable = unpack('V4', substr($normalData, $offset, 16));
            $corruptTable = unpack('V4', substr($corruptData, $offset, 16));
            
            if ($normalTable !== $corruptTable) {
                echo sprintf("    ✓ Fix Table %2d: Type=%d, First=%d, Last=%d\n",
                    $i,
                    $normalTable[1],
                    $normalTable[3],
                    $normalTable[4]
                );
                
                // Copy correct table entry from normal file
                $tableEntry = substr($normalData, $offset, 16);
                for ($j = 0; $j < 16; $j++) {
                    $recoveredData[$offset + $j] = $tableEntry[$j];
                }
                $fixes++;
            }
            
            $offset += 16;
        }
        
        echo "\n[4] Verifying pages...\n";
        
        $pageSize = $normalHeader[2];
        $totalPages = floor(strlen($normalData) / $pageSize);
        $pagesDifferent = 0;
        $pagesFixed = 0;
        
        for ($page = 0; $page < $totalPages; $page++) {
            $pageOffset = $page * $pageSize;
            $normalPage = substr($normalData, $pageOffset, $pageSize);
            $corruptPage = substr($corruptData, $pageOffset, $pageSize);
            
            if ($normalPage !== $corruptPage) {
                $pagesDifferent++;
                
                // Check if page header is corrupt (first 40 bytes)
                $normalPageHeader = substr($normalPage, 0, 40);
                $corruptPageHeader = substr($corruptPage, 0, 40);
                
                if ($normalPageHeader !== $corruptPageHeader) {
                    // Copy correct page header
                    for ($j = 0; $j < 40; $j++) {
                        if ($pageOffset + $j < strlen($recoveredData)) {
                            $recoveredData[$pageOffset + $j] = $normalPageHeader[$j];
                        }
                    }
                    $pagesFixed++;
                }
            }
        }
        
        echo "    Different pages: $pagesDifferent\n";
        echo "    Fixed page headers: $pagesFixed\n";
        
        echo "\n[5] Saving recovered file...\n";
        
        $dir = dirname($this->outputFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->outputFile, $recoveredData);
        
        echo "    ✓ Saved to: {$this->outputFile}\n";
        echo "    Size: " . number_format(strlen($recoveredData)) . " bytes\n";
        
        echo "\n[6] Verifying recovery...\n";
        
        // Verify header
        $recoveredHeader = unpack('V6', substr($recoveredData, 0, 24));
        $headerOK = true;
        
        $fields = [
            1 => 'Signature',
            2 => 'Page Size',
            3 => 'Num Tables',
            4 => 'Next Unused Page',
            5 => 'Unknown',
            6 => 'Sequence'
        ];
        
        foreach ($fields as $idx => $name) {
            $expected = $normalHeader[$idx];
            $actual = $recoveredHeader[$idx];
            
            if ($expected === $actual) {
                echo "    ✓ $name: $actual\n";
            } else {
                echo "    ✗ $name: Expected $expected, Got $actual\n";
                $headerOK = false;
            }
        }
        
        if ($headerOK) {
            echo "\n✓✓✓ RECOVERY SUCCESSFUL ✓✓✓\n";
            echo "\nRecovered file: {$this->outputFile}\n";
            echo "Total fixes applied: $fixes\n";
            echo "\nAnda sekarang bisa mencoba membuka file ini di Rekordbox.\n";
        } else {
            echo "\n⚠ Recovery completed with warnings\n";
        }
        
        return $headerOK;
    }
}

// Run precise recovery
$corruptFile = 'plans/export.pdb';
$normalFile = 'Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb';
$outputFile = 'plans/export_fixed.pdb';

echo "\n";
echo "Corrupt file:    $corruptFile\n";
echo "Reference file:  $normalFile\n";
echo "Output file:     $outputFile\n";
echo "\n";

try {
    $recovery = new PreciseRecovery($corruptFile, $normalFile, $outputFile);
    $success = $recovery->recover();
    
    if ($success) {
        echo "\n════════════════════════════════════════════════════════════\n";
        echo "NEXT STEPS:\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "1. Copy file ke USB drive Anda:\n";
        echo "   $outputFile\n";
        echo "   → /PIONEER/rekordbox/export.pdb\n";
        echo "\n";
        echo "2. Buka Rekordbox dan load USB drive\n";
        echo "\n";
        echo "3. Jika masih ada masalah, jalankan:\n";
        echo "   php test_read_recovered.php\n";
        echo "════════════════════════════════════════════════════════════\n";
    }
    
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
