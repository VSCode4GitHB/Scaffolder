<?php
$projectRoot = __DIR__ . '/..';
$phinxBinary = $projectRoot . '/vendor/bin/phinx';
$phinxConfigPath = $projectRoot . '/phinx.php';
$dbFile = $projectRoot . '/tests/var/test_schema.sqlite';
@unlink($dbFile);

echo "=== Step 1: Check migration status before running ===\n";
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$cmd = [PHP_BINARY, $phinxBinary, 'status', '-e', 'test', '-c', $phinxConfigPath];
$env = array_merge($_ENV, [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
]);

echo "Command: " . implode(' ', $cmd) . "\n";
echo "DB_NAME: $dbFile\n";

$process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

// Correction de PHPStan: Vérifier que proc_open a réussi (Ligne 30)
if ($process === false || !is_resource($process)) {
    throw new \RuntimeException("proc_open failed for command: " . implode(' ', $cmd));
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$return = proc_close($process); // Maintenant sûr

echo "Return code: $return\n";
echo "STDOUT:\n$stdout\n";

echo "\n\n=== Step 2: Now run migrate ===\n";
@unlink($dbFile);

$cmd = [PHP_BINARY, $phinxBinary, 'migrate', '-e', 'test', '-c', $phinxConfigPath];
echo "Command: " . implode(' ', $cmd) . "\n";
echo "DB_NAME: $dbFile\n";

$process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

// Correction de PHPStan: Vérifier que proc_open a réussi (Ligne 48)
if ($process === false || !is_resource($process)) {
    throw new \RuntimeException("proc_open failed for command: " . implode(' ', $cmd));
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$return = proc_close($process); // Maintenant sûr

echo "Return code: $return\n";
echo "STDOUT:\n$stdout\n";
echo "DB file exists: " . (file_exists($dbFile) ? 'YES' : 'NO') . "\n";
if (file_exists($dbFile)) {
    echo "Size: " . filesize($dbFile) . " bytes\n";
}