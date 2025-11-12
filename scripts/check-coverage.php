#!/usr/bin/env php
<?php
/**
 * Coverage checker: reads coverage.xml and ensures coverage is >= threshold
 * Usage: php scripts/check-coverage.php <threshold_percent> [coverage.xml path]
 */

$threshold = isset($argv[1]) ? (int)$argv[1] : 70;
$coveragePath = isset($argv[2]) ? $argv[2] : 'coverage.xml';

if (!file_exists($coveragePath)) {
    fprintf(STDERR, "ERROR: Coverage file not found: %s\n", $coveragePath);
    exit(1);
}

try {
    // Correction de PHPStan: Vérifier la valeur de retour de file_get_contents() (Ligne 17)
    $xmlStr = file_get_contents($coveragePath);
    if ($xmlStr === false) {
        throw new \RuntimeException("Failed to read coverage file: {$coveragePath}");
    }
    
    $xml = new SimpleXMLElement($xmlStr);
    $metrics = $xml->xpath('//metrics[@type="statement"]');

    if (empty($metrics)) {
        fprintf(STDERR, "ERROR: No statement metrics found in coverage.xml\n");
        exit(1);
    }

    $covered = 0;
    $total = 0;

    foreach ($metrics as $m) {
        $covered += (int)$m['covered'];
        $total += (int)$m['statements'];
    }

    if ($total === 0) {
        fprintf(STDERR, "ERROR: No statements found in coverage\n");
        exit(1);
    }

    $coverage = ($covered / $total) * 100;

    printf("Coverage: %.2f%% (%d/%d statements)\n", $coverage, $covered, $total);
    printf("Threshold: %d%%\n", $threshold);

    if ($coverage >= $threshold) {
        printf("✓ Coverage meets threshold\n");
        exit(0);
    } else {
        printf("✗ Coverage below threshold (%.2f%% < %d%%)\n", $coverage, $threshold);
        exit(1);
    }
} catch (Exception $e) {
    fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
    exit(1);
}