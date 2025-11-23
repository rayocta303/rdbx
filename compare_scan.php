<?php

require_once __DIR__ . '/src/Utils/DatabaseRecovery.php';
require_once __DIR__ . '/src/RekordboxReader.php';
require_once __DIR__ . '/src/Parsers/PdbParser.php';

use RekordboxReader\Utils\DatabaseRecovery;
use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Utils\Logger;

class CompareScanner {
    private $normalFile;
    private $corruptFile;
    private $normalData;
    private $corruptData;
    
    public function __construct($normalFile, $corruptFile) {
        $this->normalFile = $normalFile;
        $this->corruptFile = $corruptFile;
        
        if (!file_exists($normalFile)) {
            throw new Exception("Normal file not found: $normalFile");
        }
        if (!file_exists($corruptFile)) {
            throw new Exception("Corrupt file not found: $corruptFile");
        }
        
        $this->normalData = file_get_contents($normalFile);
        $this->corruptData = file_get_contents($corruptFile);
    }
    
    public function scan() {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  REKORDBOX DATABASE COMPARISON SCANNER                     ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        
        $this->compareBasics();
        $this->compareHeaders();
        $this->comparePageStructure();
        $this->compareTableDirectory();
        $this->compareFirstPages();
        $this->tryParseBoth();
        $this->generateRecoveryPlan();
    }
    
    private function compareBasics() {
        echo "[1] FILE SIZE COMPARISON\n";
        echo "  Normal:  " . number_format(strlen($this->normalData)) . " bytes\n";
        echo "  Corrupt: " . number_format(strlen($this->corruptData)) . " bytes\n";
        
        if (strlen($this->normalData) == strlen($this->corruptData)) {
            echo "  ✓ Same size\n";
        } else {
            echo "  ⚠ Different sizes\n";
        }
        echo "\n";
    }
    
    private function compareHeaders() {
        echo "[2] HEADER COMPARISON (bytes 0-24)\n";
        
        $normalHeader = unpack('V6', substr($this->normalData, 0, 24));
        $corruptHeader = unpack('V6', substr($this->corruptData, 0, 24));
        
        $fields = [
            1 => 'Signature',
            2 => 'Page Size',
            3 => 'Num Tables',
            4 => 'Next Unused Page',
            5 => 'Unknown',
            6 => 'Sequence'
        ];
        
        foreach ($fields as $idx => $name) {
            $normal = $normalHeader[$idx];
            $corrupt = $corruptHeader[$idx];
            $match = $normal === $corrupt ? '✓' : '✗';
            
            echo sprintf("  %-18s Normal: %-12s Corrupt: %-12s %s\n", 
                $name . ':', 
                $this->formatValue($normal, $idx),
                $this->formatValue($corrupt, $idx),
                $match
            );
        }
        echo "\n";
    }
    
    private function formatValue($val, $idx) {
        if ($idx == 1) { // Signature
            return '0x' . str_pad(dechex($val), 8, '0', STR_PAD_LEFT);
        }
        return number_format($val);
    }
    
    private function comparePageStructure() {
        echo "[3] PAGE STRUCTURE COMPARISON\n";
        
        $normalHeader = unpack('V6', substr($this->normalData, 0, 24));
        $corruptHeader = unpack('V6', substr($this->corruptData, 0, 24));
        
        $normalPageSize = $normalHeader[2];
        $corruptPageSize = $corruptHeader[2];
        
        echo "  Normal page size:  $normalPageSize bytes\n";
        echo "  Corrupt page size: $corruptPageSize bytes\n";
        
        if ($normalPageSize > 0 && $normalPageSize == $corruptPageSize) {
            $totalPages = floor(strlen($this->normalData) / $normalPageSize);
            echo "  Total pages: $totalPages\n";
            
            // Check first few pages
            $differences = 0;
            for ($i = 0; $i < min($totalPages, 10); $i++) {
                $offset = $i * $normalPageSize;
                $normalPage = substr($this->normalData, $offset, $normalPageSize);
                $corruptPage = substr($this->corruptData, $offset, $normalPageSize);
                
                if ($normalPage !== $corruptPage) {
                    $differences++;
                }
            }
            
            echo "  Pages different (first 10): $differences\n";
        }
        echo "\n";
    }
    
    private function compareTableDirectory() {
        echo "[4] TABLE DIRECTORY COMPARISON (bytes 24+)\n";
        
        $normalHeader = unpack('V6', substr($this->normalData, 0, 24));
        $corruptHeader = unpack('V6', substr($this->corruptData, 0, 24));
        
        $numTables = $normalHeader[3];
        echo "  Number of tables: $numTables\n\n";
        
        $offset = 24;
        for ($i = 0; $i < min($numTables, 10); $i++) {
            $normalTable = unpack('V4', substr($this->normalData, $offset, 16));
            $corruptTable = unpack('V4', substr($this->corruptData, $offset, 16));
            
            $match = ($normalTable == $corruptTable) ? '✓' : '✗';
            
            echo sprintf("  Table %2d: Type=%-3s First=%-5s Last=%-5s %s\n",
                $i,
                $normalTable[1],
                $normalTable[3],
                $normalTable[4],
                $match
            );
            
            if ($normalTable != $corruptTable) {
                echo sprintf("           Corrupt: Type=%-3s First=%-5s Last=%-5s\n",
                    $corruptTable[1],
                    $corruptTable[3],
                    $corruptTable[4]
                );
            }
            
            $offset += 16;
        }
        echo "\n";
    }
    
    private function compareFirstPages() {
        echo "[5] BYTE-BY-BYTE COMPARISON (first 1024 bytes)\n";
        
        $checkLen = min(1024, strlen($this->normalData), strlen($this->corruptData));
        $differences = 0;
        $firstDiff = -1;
        
        for ($i = 0; $i < $checkLen; $i++) {
            if ($this->normalData[$i] !== $this->corruptData[$i]) {
                $differences++;
                if ($firstDiff == -1) {
                    $firstDiff = $i;
                }
            }
        }
        
        echo "  Bytes checked: $checkLen\n";
        echo "  Differences:   $differences\n";
        
        if ($firstDiff >= 0) {
            echo "  First diff at: byte $firstDiff\n";
            echo "    Normal:  0x" . bin2hex(substr($this->normalData, $firstDiff, 16)) . "\n";
            echo "    Corrupt: 0x" . bin2hex(substr($this->corruptData, $firstDiff, 16)) . "\n";
        } else {
            echo "  ✓ First 1024 bytes are identical!\n";
        }
        echo "\n";
    }
    
    private function tryParseBoth() {
        echo "[6] TRYING TO PARSE WITH PdbParser\n";
        
        $logger = new Logger('output', false);
        
        // Try normal file
        echo "\n  [NORMAL FILE]\n";
        try {
            $parser = new PdbParser($this->normalFile, $logger);
            $data = $parser->parse();
            echo "    ✓ Parsed successfully\n";
            echo "    - Tables: " . count($data['tables']) . "\n";
            echo "    - Page size: " . $data['page_size'] . "\n";
        } catch (Exception $e) {
            echo "    ✗ Parse failed: " . $e->getMessage() . "\n";
        }
        
        // Try corrupt file
        echo "\n  [CORRUPT FILE]\n";
        try {
            $parser = new PdbParser($this->corruptFile, $logger);
            $data = $parser->parse();
            echo "    ✓ Parsed successfully\n";
            echo "    - Tables: " . count($data['tables']) . "\n";
            echo "    - Page size: " . $data['page_size'] . "\n";
        } catch (Exception $e) {
            echo "    ✗ Parse failed: " . $e->getMessage() . "\n";
            echo "    Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function generateRecoveryPlan() {
        echo "[7] RECOVERY PLAN\n";
        
        $normalHeader = unpack('V6', substr($this->normalData, 0, 24));
        $corruptHeader = unpack('V6', substr($this->corruptData, 0, 24));
        
        $steps = [];
        
        // Check each field
        if ($normalHeader[1] !== $corruptHeader[1]) {
            $steps[] = "Fix signature (copy from normal: 0x" . dechex($normalHeader[1]) . ")";
        }
        if ($normalHeader[2] !== $corruptHeader[2]) {
            $steps[] = "Fix page size (copy from normal: " . $normalHeader[2] . ")";
        }
        if ($normalHeader[3] !== $corruptHeader[3]) {
            $steps[] = "Fix num tables (copy from normal: " . $normalHeader[3] . ")";
        }
        if ($normalHeader[4] !== $corruptHeader[4]) {
            $steps[] = "Fix next unused page (copy from normal: " . $normalHeader[4] . ")";
        }
        if ($normalHeader[5] !== $corruptHeader[5]) {
            $steps[] = "Fix unknown field (copy from normal: " . $normalHeader[5] . ")";
        }
        if ($normalHeader[6] !== $corruptHeader[6]) {
            $steps[] = "Fix sequence (copy from normal: " . $normalHeader[6] . ")";
        }
        
        if (count($steps) == 0) {
            echo "  ✓ Header appears correct - corruption may be in pages/data\n";
            echo "  → Need to compare individual pages and table data\n";
        } else {
            echo "  Required fixes:\n";
            foreach ($steps as $idx => $step) {
                echo "  " . ($idx + 1) . ". $step\n";
            }
        }
        
        echo "\n";
    }
}

// Run comparison
$normalFile = 'Rekordbox-USB-Corrupted/PIONEER/rekordbox/export-normal.pdb';
$corruptFile = 'plans/export.pdb';

try {
    $scanner = new CompareScanner($normalFile, $corruptFile);
    $scanner->scan();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
