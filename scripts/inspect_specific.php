<?php
$f = __DIR__ . '/../tests/var/test_schema.sqlite.sqlite3';
if (!file_exists($f)) {
    echo "MISSING\n";
    exit(0);
}
try {
    $pdo = new PDO('sqlite:' . $f);
    $rows = $pdo->query("SELECT name, type FROM sqlite_master WHERE type IN ('table') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo $r['name'] . "\n";
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . "\n";
}
