<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/../src/RekordboxReader.php';

use RekordboxReader\RekordboxReader;

try {
    $exportPath = isset($_GET['path']) ? $_GET['path'] : './Rekordbox-USB';
    
    if (!is_dir($exportPath)) {
        throw new Exception("Export path not found: " . $exportPath);
    }

    $reader = new RekordboxReader($exportPath, 'output', false);
    $result = $reader->run();

    $response = [
        'success' => true,
        'data' => $result,
        'stats' => $reader->getStats()
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
