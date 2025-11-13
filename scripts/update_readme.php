<?php
declare(strict_types=1);

// Simple script to update README.md with a "Dernière mise à jour" timestamp
$readme = __DIR__ . '/../README.md';
if (!file_exists($readme)) {
    fwrite(STDERR, "README.md not found\n");
    exit(1);
}

// Correction de PHPStan: Vérifier la valeur de retour de file_get_contents() (Ligne 15, 16, 19)
$contents = file_get_contents($readme);
if ($contents === false) {
    fwrite(STDERR, "Failed to read README.md\n");
    exit(1);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

// If a line starting with "Dernière mise à jour" exists, replace it
if (preg_match('/^Dernière mise à jour\s*:\s*.*/m', $contents)) {
    $contents = preg_replace('/^Dernière mise à jour\s*:\s*.*/m', "Dernière mise à jour : $now", $contents);
} else {
    // Insert after first H1 heading
    $contents = preg_replace('/^(#\s.*)$/m', "$1\n\nDernière mise à jour : $now", $contents, 1);
}

file_put_contents($readme, $contents);
echo "README.md updated with timestamp: $now\n";