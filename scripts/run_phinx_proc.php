<?php
declare(strict_types=1);

$projectRoot = __DIR__ . '/..';
$phinxBinary = $projectRoot . '/vendor/bin/phinx';
$phinxConfigPath = $projectRoot . '/phinx.php';
$dbFile = $projectRoot . '/tests/var/test_schema.sqlite';
if (file_exists($dbFile)) {
    unlink($dbFile);
}

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

echo "Command: " . implode(' ', array_map('escapeshellarg', $cmd)) . "\n";
echo "DB_NAME: $dbFile\n";

// Validate phinx binary before attempting to open process
if (!file_exists($phinxBinary) || !is_executable($phinxBinary)) {
    echo "ERROR: phinx binary not found or not executable: $phinxBinary\n";
    exit(1);
}

$process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

// Vérification ajoutée
if ($process === false || !is_resource($process)) {
    echo "ERROR: Could not open process for status command.\n";
    exit(1);
}

// Safely read pipes if they exist and are resources
$stdout = '';
$stderr = '';
if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}
if (isset($pipes[1]) && is_resource($pipes[1])) {
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
}
if (isset($pipes[2]) && is_resource($pipes[2])) {
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
}

$return = proc_close($process);

echo "Return code: $return\n";
echo "STDOUT:\n$stdout\n";
if ($stderr !== '') {
    echo "STDERR:\n$stderr\n";
}

echo "\n\n=== Step 2: Now run migrate ===\n";
if (file_exists($dbFile)) {
    unlink($dbFile);
}

$cmd = [PHP_BINARY, $phinxBinary, 'migrate', '-e', 'test', '-c', $phinxConfigPath];
echo "Command: " . implode(' ', array_map('escapeshellarg', $cmd)) . "\n";
echo "DB_NAME: $dbFile\n";

$process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

// Vérification ajoutée
if ($process === false || !is_resource($process)) {
    echo "ERROR: Could not open process for migrate command.\n";
    exit(1);
}

$stdout = '';
$stderr = '';
if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}
if (isset($pipes[1]) && is_resource($pipes[1])) {
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
}
if (isset($pipes[2]) && is_resource($pipes[2])) {
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
}

$return = proc_close($process);

echo "Return code: $return\n";
echo "STDOUT:\n$stdout\n";
if ($stderr !== '') {
    echo "STDERR:\n$stderr\n";
}
echo "DB file exists: " . (file_exists($dbFile) ? 'YES' : 'NO') . "\n";
if (file_exists($dbFile)) {
    echo "Size: " . filesize($dbFile) . " bytes\n";
}
