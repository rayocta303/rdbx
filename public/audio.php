<?php
// Audio streaming endpoint
// Serves audio files from Rekordbox-USB directory

$filePath = $_GET['path'] ?? '';

if (empty($filePath)) {
    http_response_code(400);
    die('No file path provided');
}

// Security: prevent directory traversal
$filePath = str_replace('..', '', $filePath);
$fullPath = __DIR__ . '/../Rekordbox-USB' . $filePath;

if (!file_exists($fullPath)) {
    http_response_code(404);
    die('Audio file not found');
}

if (!is_file($fullPath)) {
    http_response_code(400);
    die('Invalid file');
}

// Get file info
$fileSize = filesize($fullPath);
$mimeType = mime_content_type($fullPath);

// Set headers for audio streaming
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

// Support for range requests (seeking in audio)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    list($start, $end) = explode('-', $range);
    
    $start = intval($start);
    $end = $end ? intval($end) : $fileSize - 1;
    $length = $end - $start + 1;
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
    header("Content-Length: $length");
    
    $file = fopen($fullPath, 'rb');
    fseek($file, $start);
    echo fread($file, $length);
    fclose($file);
} else {
    // Stream entire file
    readfile($fullPath);
}
