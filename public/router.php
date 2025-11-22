<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$uri = rtrim($uri, '/');
if (empty($uri)) {
    $uri = '/';
}

$routes = [
    '/debug' => __DIR__ . '/pages/debug.php',
    '/table' => __DIR__ . '/pages/table.php',
    '/' => __DIR__ . '/index.php',
];

if (isset($routes[$uri])) {
    require $routes[$uri];
    return true;
}

if (preg_match('/\.(?:png|jpg|jpeg|gif|ico|css|js|svg|woff|woff2|ttf|eot|mp3|wav|ogg)$/', $uri)) {
    return false;
}

if (file_exists(__DIR__ . $uri)) {
    return false;
}

http_response_code(404);
echo "404 - Page Not Found";
return true;
