<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Simple router for dev: map to templates or controllers
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/' || $path === '') {
    echo 'App root. Use /health or your controllers.';
    exit;
}

if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

// fallback
http_response_code(404);
echo 'Not found';
