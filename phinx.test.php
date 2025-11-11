<?php
/**
 * Phinx Configuration pour les tests d'intégration
 * Utilise SQLite avec une table de migration dédiée pour éviter les conflits
 */
return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
        'seeds' => __DIR__ . '/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog_test',
        'default_environment' => 'test',
        'test' => [
            'adapter' => 'sqlite',
            'name' => __DIR__ . '/tests/var/test_schema.sqlite',
            'memory' => false,
        ],
    ],
    'version_order' => 'creation',
];
