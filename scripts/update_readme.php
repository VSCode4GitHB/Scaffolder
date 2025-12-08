<?php
declare(strict_types=1);

// Simple script to update README.md with a "Dernière mise à jour" timestamp
$readme = __DIR__ . '/../README.md';
if (!file_exists($readme)) {
    fwrite(STDERR, "README.md not found\n");
    exit(1);
}

$contents = file_get_contents($readme);

// Vérification ajoutée
if ($contents === false) {
    fwrite(STDERR, "Could not read README.md\n");
    exit(1);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

// If a line starting with "Dernière mise à jour" exists, replace it
if (preg_match('/^Dernière mise à jour\s*:\s*.*/m', $contents)) {
    $newContents = preg_replace('/^Dernière mise à jour\s*:\s*.*/m', "Dernière mise à jour : $now", $contents);
    if ($newContents === null) {
        fwrite(STDERR, "Regex error while replacing timestamp\n");
        exit(1);
    }
    $contents = $newContents;
} else {
    // Insert after first H1 heading
    $newContents = preg_replace('/^(#\s.*)$/m', "$1\n\nDernière mise à jour : $now", $contents, 1);
    if ($newContents === null) {
        fwrite(STDERR, "Regex error while inserting timestamp\n");
        exit(1);
    }
    $contents = $newContents;
}

$result = file_put_contents($readme, $contents);
if ($result === false) {
    fwrite(STDERR, "Failed to write README.md\n");
    exit(1);
}

echo "README.md updated with timestamp: $now\n";
