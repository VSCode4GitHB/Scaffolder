<?php
// scripts/aggregate_progress.php
// Simple CLI to summarize docs/progress_entries.jsonl
// Usage: php scripts/aggregate_progress.php [path]

$path = $argv[1] ?? __DIR__ . '/../docs/progress_entries.jsonl';
if (!file_exists($path)) {
    fwrite(STDERR, "File not found: $path\n");
    exit(2);
}

$counts = [
    'total' => 0,
    'by_phase' => [],
    'by_status' => [],
    'by_owner' => [],
];

$fh = fopen($path, 'r');
if ($fh === false) {
    fwrite(STDERR, "Could not open file: $path\n");
    exit(3);
}
while (($line = fgets($fh)) !== false) {
    $line = trim($line);
    if ($line === '') continue;
    $obj = json_decode($line, true);
    if (!is_array($obj)) continue;
    $counts['total']++;
    $phase = $obj['phase'] ?? 'unknown';
    $status = $obj['status'] ?? 'unknown';
    $owner = $obj['owner'] ?? 'unknown';
    $counts['by_phase'][$phase] = ($counts['by_phase'][$phase] ?? 0) + 1;
    $counts['by_status'][$status] = ($counts['by_status'][$status] ?? 0) + 1;
    $counts['by_owner'][$owner] = ($counts['by_owner'][$owner] ?? 0) + 1;
}
fclose($fh);

echo "Progress aggregation for: $path\n";
echo "Total entries: " . $counts['total'] . "\n\n";
echo "By phase:\n";
foreach ($counts['by_phase'] as $k => $v) {
    echo "  - $k: $v\n";
}

echo "\nBy status:\n";
foreach ($counts['by_status'] as $k => $v) {
    echo "  - $k: $v\n";
}

echo "\nTop owners:\n";
arsort($counts['by_owner']);
$top = array_slice($counts['by_owner'], 0, 10, true);
foreach ($top as $k => $v) {
    echo "  - $k: $v\n";
}

exit(0);
