<?php

require_once __DIR__ . '/src/Utils/DatabaseRecovery.php';

use RekordboxReader\Utils\DatabaseRecovery;

class SimpleLogger {
    public function debug($message) {
        echo "[DEBUG] $message\n";
    }
    
    public function info($message) {
        echo "[INFO] $message\n";
    }
    
    public function error($message) {
        echo "[ERROR] $message\n";
    }
}

$logger = new SimpleLogger();

// Files untuk recovery
$corruptDb = __DIR__ . '/plans/export.pdb';
$recoveredDb = __DIR__ . '/plans/export_recovered.pdb';
$referenceDb = null; // Tidak ada reference database

echo "========================================\n";
echo "RECOVERY FILE CORRUPT: plans/export.pdb\n";
echo "========================================\n\n";

if (!file_exists($corruptDb)) {
    echo "ERROR: File corrupt tidak ditemukan: $corruptDb\n";
    exit(1);
}

echo "File Corrupt:    $corruptDb\n";
echo "File Recovered:  $recoveredDb\n";
echo "Reference DB:    " . ($referenceDb ? $referenceDb : "NONE") . "\n\n";

// Create recovery instance
$recovery = new DatabaseRecovery($corruptDb, $recoveredDb, $referenceDb, $logger);

echo "=== STEP 1: Scanning database untuk analisa ===\n\n";

try {
    $scanResults = $recovery->scanDatabase();
    
    echo "\n=== HASIL SCAN ===\n\n";
    
    // Display summary
    if (isset($scanResults['summary'])) {
        $summary = $scanResults['summary'];
        echo "Overall Health: " . strtoupper($summary['overall_health']) . "\n";
        echo "Total Issues: " . $summary['total_issues'] . "\n";
        echo "Critical Issues: " . $summary['critical_issues'] . "\n";
        echo "Warnings: " . $summary['warnings'] . "\n\n";
        
        if (!empty($summary['recommendations'])) {
            echo "Rekomendasi:\n";
            foreach ($summary['recommendations'] as $rec) {
                echo "  - $rec\n";
            }
            echo "\n";
        }
    }
    
    // Display specific issues
    echo "Detail Issues:\n\n";
    
    if (isset($scanResults['header']['issues']) && count($scanResults['header']['issues']) > 0) {
        echo "  [HEADER]\n";
        foreach ($scanResults['header']['issues'] as $issue) {
            echo "    • $issue\n";
        }
    }
    
    if (isset($scanResults['metadata']['issues']) && count($scanResults['metadata']['issues']) > 0) {
        echo "  [METADATA]\n";
        foreach ($scanResults['metadata']['issues'] as $issue) {
            echo "    • $issue\n";
        }
    }
    
    if (isset($scanResults['pages']['issues']) && count($scanResults['pages']['issues']) > 0) {
        echo "  [PAGES]\n";
        foreach ($scanResults['pages']['issues'] as $issue) {
            echo "    • $issue\n";
        }
    }
    
    echo "\n=== STEP 2: Menjalankan Full Recovery ===\n\n";
    
    $result = $recovery->recoverAll();
    
    echo "\n========================================\n";
    echo "RECOVERY RESULT: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    echo "========================================\n\n";
    
    // Get recovery logs
    $log = $recovery->getRecoveryLog();
    echo "Recovery Actions:\n";
    foreach ($log as $entry) {
        if (strpos($entry, '===') !== false) {
            echo "\n$entry\n";
        } else {
            echo "  • $entry\n";
        }
    }
    
    // Get stats
    $stats = $recovery->getStats();
    echo "\nStatistik:\n";
    echo "  File asli:      " . number_format($stats['corrupt_size']) . " bytes\n";
    echo "  File recovered: " . number_format($stats['recovered_size']) . " bytes\n";
    echo "  Log entries:    " . $stats['log_entries'] . "\n";
    
    // Check if recovered file exists
    if (file_exists($recoveredDb)) {
        echo "\n✓ File recovered berhasil dibuat: $recoveredDb\n";
        echo "\nAnda bisa mencoba membuka file ini di Rekordbox:\n";
        echo "  $recoveredDb\n\n";
        echo "Jika masih ada masalah, file ini sudah diperbaiki sebisa mungkin.\n";
        echo "Beberapa data mungkin hilang karena tingkat korupsi.\n";
    } else {
        echo "\n✗ File recovered tidak berhasil dibuat!\n";
    }
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "========================================\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
