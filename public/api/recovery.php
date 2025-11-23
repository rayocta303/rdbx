<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../src/Utils/DatabaseCorruptor.php';
require_once __DIR__ . '/../../src/Utils/DatabaseRecovery.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

use RekordboxReader\Utils\DatabaseCorruptor;
use RekordboxReader\Utils\DatabaseRecovery;
use RekordboxReader\Utils\Logger;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $action = $input['action'] ?? '';
    $logger = new Logger('output', true);

    switch ($action) {
        case 'corrupt':
            $scenarios = $input['scenarios'] ?? [];
            $sourceDb = $input['sourceDb'] ?? '';
            $targetDb = $input['targetDb'] ?? '';

            if (empty($scenarios)) {
                throw new Exception('No scenarios selected');
            }

            if (empty($sourceDb) || empty($targetDb)) {
                throw new Exception('Source and target databases required');
            }

            // Convert to absolute paths from project root
            $sourceDb = __DIR__ . '/../../' . $sourceDb;
            $targetDb = __DIR__ . '/../../' . $targetDb;

            $corruptor = new DatabaseCorruptor($sourceDb, $targetDb, $logger);
            
            foreach ($scenarios as $scenario) {
                switch ($scenario) {
                    case 1: $corruptor->corruptMagicHeader(); break;
                    case 2: $corruptor->corruptMetadataHeader(); break;
                    case 3: $corruptor->corruptPageHeaders(); break;
                    case 4: $corruptor->corruptRowPresenceBitmap(); break;
                    case 5: $corruptor->corruptTableIndex(); break;
                    case 6: $corruptor->corruptRowStructure(); break;
                    case 7: $corruptor->corruptFieldData(); break;
                    case 8: $corruptor->corruptPlaylistStructure(); break;
                    case 9: $corruptor->corruptCrossTableRelationships(); break;
                    case 10: $corruptor->corruptVersionInfo(); break;
                }
            }

            $info = $corruptor->getInfo();

            echo json_encode([
                'success' => true,
                'scenarios' => $scenarios,
                'info' => $info,
                'message' => 'Database corrupted with ' . count($scenarios) . ' scenarios'
            ]);
            break;

        case 'recover':
            $method = intval($input['method'] ?? 0);
            $corruptDb = $input['corruptDb'] ?? '';
            $recoveredDb = $input['recoveredDb'] ?? '';
            $referenceDb = $input['referenceDb'] ?? null;

            if ($method < 1 || $method > 10) {
                throw new Exception('Invalid recovery method');
            }

            if (empty($corruptDb) || empty($recoveredDb)) {
                throw new Exception('Corrupt and recovered database paths required');
            }

            // Convert to absolute paths from project root
            $corruptDb = __DIR__ . '/../../' . $corruptDb;
            $recoveredDb = __DIR__ . '/../../' . $recoveredDb;
            if (!empty($referenceDb)) {
                $referenceDb = __DIR__ . '/../../' . $referenceDb;
            }

            $recovery = new DatabaseRecovery($corruptDb, $recoveredDb, $referenceDb, $logger);
            
            $result = false;
            switch ($method) {
                case 1: $result = $recovery->recoverMagicHeader(); break;
                case 2: $result = $recovery->recoverMetadataHeader(); break;
                case 3: $result = $recovery->recoverPageHeaders(); break;
                case 4: $result = $recovery->recoverRowPresenceBitmap(); break;
                case 5: $result = $recovery->recoverTableIndex(); break;
                case 6: $result = $recovery->recoverRowStructure(); break;
                case 7: $result = $recovery->recoverFieldData(); break;
                case 8: $result = $recovery->recoverPlaylistStructure(); break;
                case 9: $result = $recovery->recoverCrossTableRelationships(); break;
                case 10: $result = $recovery->recoverVersionInfo(); break;
            }

            $log = $recovery->getRecoveryLog();
            $stats = $recovery->getStats();

            echo json_encode([
                'success' => true,
                'method' => $method,
                'result' => $result,
                'log' => $log,
                'stats' => $stats
            ]);
            break;

        case 'recover_all':
            $corruptDb = $input['corruptDb'] ?? '';
            $recoveredDb = $input['recoveredDb'] ?? '';
            $referenceDb = $input['referenceDb'] ?? null;

            if (empty($corruptDb) || empty($recoveredDb)) {
                throw new Exception('Corrupt and recovered database paths required');
            }

            // Convert to absolute paths from project root
            $corruptDb = __DIR__ . '/../../' . $corruptDb;
            $recoveredDb = __DIR__ . '/../../' . $recoveredDb;
            if (!empty($referenceDb)) {
                $referenceDb = __DIR__ . '/../../' . $referenceDb;
            }

            $recovery = new DatabaseRecovery($corruptDb, $recoveredDb, $referenceDb, $logger);
            $result = $recovery->recoverAll();

            $log = $recovery->getRecoveryLog();
            $stats = $recovery->getStats();

            echo json_encode([
                'success' => true,
                'result' => $result,
                'log' => $log,
                'stats' => $stats,
                'message' => 'Full recovery process completed'
            ]);
            break;

        case 'scan':
            $sourceDb = $input['sourceDb'] ?? '';
            $referenceDb = $input['referenceDb'] ?? null;

            if (empty($sourceDb)) {
                throw new Exception('Source database path required');
            }

            // Convert to absolute paths from project root
            $sourceDb = __DIR__ . '/../../' . $sourceDb;
            if (!empty($referenceDb)) {
                $referenceDb = __DIR__ . '/../../' . $referenceDb;
            }

            if (!file_exists($sourceDb)) {
                throw new Exception('Source database not found: ' . $sourceDb);
            }

            // Use a temporary recovered path (not actually used for scanning)
            $tempRecoveredPath = dirname($sourceDb) . '/temp_scan.pdb';
            
            $recovery = new DatabaseRecovery($sourceDb, $tempRecoveredPath, $referenceDb, $logger);
            $scanResults = $recovery->scanDatabase();
            $log = $recovery->getRecoveryLog();

            echo json_encode([
                'success' => true,
                'scan_results' => $scanResults,
                'log' => $log,
                'message' => 'Database scan completed'
            ]);
            break;

        case 'list_files':
            $baseDir = __DIR__ . '/../../';
            $files = [];
            
            // Whitelist of allowed directories to scan (security: prevent full filesystem enumeration)
            $allowedDirs = [
                'Rekordbox-USB/PIONEER/rekordbox',
                'Rekordbox-USB-Corrupted/PIONEER/rekordbox'
            ];
            
            foreach ($allowedDirs as $allowedDir) {
                $scanPath = $baseDir . $allowedDir;
                
                if (!is_dir($scanPath)) {
                    continue;
                }
                
                try {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($scanPath, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    
                    foreach ($iterator as $file) {
                        if ($file->isFile() && strtolower($file->getExtension()) === 'pdb') {
                            $fullPath = $file->getPathname();
                            $relativePath = str_replace($baseDir, '', $fullPath);
                            
                            // Additional safety: ensure path is within allowed directories
                            $isAllowed = false;
                            foreach ($allowedDirs as $allowed) {
                                if (strpos($relativePath, $allowed) === 0) {
                                    $isAllowed = true;
                                    break;
                                }
                            }
                            
                            if (!$isAllowed) {
                                continue;
                            }
                            
                            $fileSize = $file->getSize();
                            $sizeFormatted = $fileSize < 1024 ? $fileSize . ' B' :
                                            ($fileSize < 1024*1024 ? round($fileSize/1024, 1) . ' KB' :
                                            round($fileSize/1024/1024, 1) . ' MB');
                            
                            $files[] = [
                                'name' => $file->getFilename(),
                                'path' => $relativePath,
                                'size' => $sizeFormatted,
                                'size_bytes' => $fileSize,
                                'modified' => date('Y-m-d H:i:s', $file->getMTime())
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // Skip directories that can't be read
                    continue;
                }
            }
            
            // Sort by path
            usort($files, function($a, $b) {
                return strcmp($a['path'], $b['path']);
            });
            
            echo json_encode([
                'success' => true,
                'files' => $files,
                'count' => count($files),
                'message' => 'Found ' . count($files) . ' database files',
                'scanned_directories' => $allowedDirs
            ]);
            break;

        case 'test_corruption':
            $corruptDb = __DIR__ . '/../../Rekordbox-USB-Corrupted/PIONEER/rekordbox/export.pdb';
            
            if (!file_exists($corruptDb)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Corrupted database not found. Please corrupt a database first.'
                ]);
                break;
            }

            $data = file_get_contents($corruptDb);
            $header = unpack('V6data', substr($data, 0, 24));
            
            $isCorrupt = false;
            $issues = [];

            if ($header['data1'] != 0) {
                $isCorrupt = true;
                $issues[] = 'Invalid signature';
            }

            if ($header['data2'] < 512 || $header['data2'] > 16384) {
                $isCorrupt = true;
                $issues[] = 'Invalid page size: ' . $header['data2'];
            }

            if ($header['data3'] > 100) {
                $isCorrupt = true;
                $issues[] = 'Suspicious table count: ' . $header['data3'];
            }

            echo json_encode([
                'success' => true,
                'is_corrupt' => $isCorrupt,
                'issues' => $issues,
                'header' => $header,
                'file_size' => strlen($data)
            ]);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
