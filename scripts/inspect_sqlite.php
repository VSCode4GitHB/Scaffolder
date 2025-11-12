<?php
$path = __DIR__ . '/../tests/var/test_schema.sqlite';
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}
$pdo = new PDO('sqlite:' . $path);

// Correction de PHPStan: Vérifier la valeur de retour de PDO::query() (Ligne 9)
$stmt = $pdo->query("SELECT name, type, sql FROM sqlite_master WHERE type IN ('table','index') ORDER BY type, name");

if ($stmt === false) {
    $info = $pdo->errorInfo();
    $msg = $info[2] ?? 'Unknown DB query error';
    echo "DB query failed: $msg\n";
    exit(1);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);