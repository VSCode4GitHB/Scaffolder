<?php
declare(strict_types=1);

// 1. Chargement du bootstrap principal
require_once dirname(__DIR__) . '/config/bootstrap.php';

// 2. Configuration spécifique aux tests
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_NAME'] = ':memory:';

// 3. Nettoyage des caches et logs de test
@unlink(__DIR__ . '/var/cache/test.cache');
@unlink(__DIR__ . '/var/logs/test.log');

// 4. Configuration des mocks si nécessaire
if ($_ENV['DEV_MOCK_EXTERNAL_SERVICES'] ?? false) {
    // Configuration des mocks pour les services externes
}