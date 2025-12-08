#!/usr/bin/env php
<?php
declare(strict_types=1);
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
    $fileContents = file_get_contents($coveragePath);
    if ($fileContents === false) {
        fprintf(STDERR, "ERROR: Could not read coverage file: %s\n", $coveragePath);
        exit(1);
    }

    // Parse XML safely and report libxml errors
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($fileContents);
    if ($xml === false) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        $msg = "Invalid XML in coverage file";
        if (!empty($errors)) {
            $first = $errors[0];
            $msg .= sprintf(" (line %d: %s)", $first->line ?? 0, trim($first->message ?? ''));
        }
        fprintf(STDERR, "ERROR: %s: %s\n", $msg, $coveragePath);
        exit(1);
    }

    // Prefer Clover project-level metrics (PHPUnit 10)
    $projectMetrics = $xml->xpath('/coverage/project/metrics');

    $covered = 0;
    $total   = 0;

    if (!empty($projectMetrics) && is_array($projectMetrics)) {
        $m = $projectMetrics[0];
        $total   = (int)($m['statements'] ?? 0);
        $covered = (int)($m['coveredstatements'] ?? 0);
    } else {
        // Fallback: sum class-level metrics if present
        $classMetrics = $xml->xpath('//file/class/metrics');
        if (!empty($classMetrics) && is_array($classMetrics)) {
            foreach ($classMetrics as $m) {
                $total   += (int)($m['statements'] ?? 0);
                $covered += (int)($m['coveredstatements'] ?? 0);
            }
        } else {
            // Last resort: sum file-level metrics
            $fileMetrics = $xml->xpath('//file/metrics');
            if (!empty($fileMetrics) && is_array($fileMetrics)) {
                foreach ($fileMetrics as $m) {
                    $total   += (int)($m['statements'] ?? 0);
                    $covered += (int)($m['coveredstatements'] ?? 0);
                }
            }
        }
    }

    if ($total === 0) {
        fprintf(STDERR, "ERROR: No statements found in coverage (parsed total=0)\n");
        exit(1);
    }

    $coverage = $total > 0 ? ($covered / $total) * 100 : 0.0;

    printf("Coverage: %.2f%% (%d/%d statements)\n", $coverage, $covered, $total);
    printf("Threshold: %d%%\n", $threshold);

    if ($coverage >= $threshold) {
        printf("✓ Coverage meets threshold\n");
        exit(0);
    } else {
        printf("✗ Coverage below threshold (%.2f%% < %d%%)\n", $coverage, $threshold);
        exit(1);
    }
} catch (\Throwable $e) {
    fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
    exit(1);
}
