<?php
$f = __DIR__ . '/../tests/var/test_schema.sqlite.sqlite3';
if (!file_exists($f)) {
    echo "MISSING\n";
    exit(0);
}
try {
    $pdo = new PDO('sqlite:' . $f);
    
    // Correction de PHPStan: Vérifier la valeur de retour de PDO::query() (Ligne 9)
    $stmt = $pdo->query("SELECT name, type FROM sqlite_master WHERE type IN ('table') ORDER BY name");
    
    if ($stmt === false) {
        $info = $pdo->errorInfo();
        $msg = $info[2] ?? 'Unknown DB query error';
        throw new \RuntimeException("DB query failed: {$msg}");
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo $r['name'] . "\n";
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . "\n";
}