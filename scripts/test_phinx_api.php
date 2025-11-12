<?php
// Quick test: can we load Phinx and run migrations programmatically?
require_once __DIR__ . '/../vendor/autoload.php';

$dbFile = __DIR__ . '/tests/var/quick_test.sqlite';
@unlink($dbFile);

// Create a minimal config array
$config = [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'test',
        'test' => [
            'adapter' => 'sqlite',
            'name' => $dbFile,
            'memory' => false,
        ],
    ],
    'version_order' => 'creation'
];

try {
    $phinxConfig = new \Phinx\Config\Config($config);
    echo "Config created OK\n";
    echo "Config methods: " . implode(', ', array_filter(get_class_methods($phinxConfig), fn($m) => !str_starts_with($m, '_'))) . "\n";
    
    // Check what Manager constructor needs
    $refl = new ReflectionClass('Phinx\Migration\Manager');
    echo "Manager constructor: ";
    foreach ($refl->getConstructor()?->getParameters() ?? [] as $p) {
        echo $p->getName() . " (" . $p->getType() . "), ";
    }
    echo "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
