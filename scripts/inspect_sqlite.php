<?php
declare(strict_types=1);

$path = __DIR__ . '/../tests/var/test_schema.sqlite';
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $path);
    // Defensive: ensure exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT name, type, sql FROM sqlite_master WHERE type IN ('table','index') ORDER BY type, name");

    // Vérification ajoutée
    if ($stmt === false) {
        echo 'Error: PDO query failed' . "\n";
        exit(1);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
