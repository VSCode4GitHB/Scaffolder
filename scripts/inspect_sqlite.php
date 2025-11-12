<?php
$path = __DIR__ . '/../tests/var/test_schema.sqlite';
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}
$pdo = new PDO('sqlite:' . $path);
$stmt = $pdo->query("SELECT name, type, sql FROM sqlite_master WHERE type IN ('table','index') ORDER BY type, name");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
