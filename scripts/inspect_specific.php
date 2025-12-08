<?php
declare(strict_types=1);

$f = __DIR__ . '/../tests/var/test_schema.sqlite.sqlite3';
if (!file_exists($f)) {
    echo "MISSING\n";
    exit(0);
}

try {
    $pdo = new PDO('sqlite:' . $f);
    // Defensive: ensure PDO throws exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT name, type FROM sqlite_master WHERE type IN ('table') ORDER BY name");

    // Vérification ajoutée
    if ($stmt === false) {
        echo 'ERR: PDO query failed' . "\n";
        exit(1);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo $r['name'] . "\n";
    }
} catch (\Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . "\n";
    exit(1);
}
