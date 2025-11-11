<?php

declare(strict_types=1);

// Minimal bootstrap for local dev and static analysis
ini_set('display_errors', '1');
error_reporting(E_ALL);
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new \RuntimeException('Autoload not found. Run `composer install` first.');
}

require $autoload;
// Basic exception handler for clearer output during development
set_exception_handler(function (\Throwable $e) {

    http_response_code(500);
    fwrite(STDERR, "Uncaught exception: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
});
