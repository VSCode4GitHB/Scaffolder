<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
        'seeds' => __DIR__ . '/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? 'cywsdb',
            'user' => $_ENV['DB_USER'] ?? 'webmaster@cyws',
            'pass' => $_ENV['DB_PASS'] ?? 'cy025@ws_PJ',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ],
        'test' => [
            'adapter' => 'sqlite',
            // Prefer getenv() (inherited process env) then $_ENV (Dotenv), fallback to in-memory
            'name' => getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ':memory:'),
            'memory' => (getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ':memory:')) === ':memory:',
        ],
        'production' => [
            'adapter' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? 'cywsdb',
            'user' => $_ENV['DB_USER'] ?? 'webmaster@cyws',
            'pass' => $_ENV['DB_PASS'] ?? 'cy025@ws_PJ',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ]
    ],
    'version_order' => 'creation'
];
