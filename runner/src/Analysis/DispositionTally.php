<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

use RuntimeException;

final class DispositionTally
{
    /**
     * Tally all rows in JSONL, counting superseded attempts (no dedup by run_id).
     *
     * @return array<string, array<string, array<string, int>>>
     *   Shape: [task_id => [model_tier => [dispatch_disposition => count]]]
     *   Missing dispatch_disposition defaults to 'completed'.
     *   Missing task_id or model_tier are cast to empty string.
     *
     * @throws RuntimeException if file is missing
     */
    public function tally(string $jsonlPath): array
    {
        if (!is_file($jsonlPath)) {
            throw new RuntimeException("Results file missing: {$jsonlPath}");
        }

        $handle = fopen($jsonlPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open results file: {$jsonlPath}");
        }

        $tally = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                // Decode line as raw array (not via ResultsRow::fromArray)
                // to handle superseded rows that may predate required fields
                try {
                    /** @var mixed $data */
                    $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    // Skip lines that don't decode to valid JSON
                    continue;
                }

                // Skip non-array data
                if (!is_array($data)) {
                    continue;
                }

                // Extract fields, with defaults
                $taskId = (string) ($data['task_id'] ?? '');
                $modelTier = (string) ($data['model_tier'] ?? '');
                $disposition = (string) ($data['dispatch_disposition'] ?? 'completed');

                // Initialize nested structure if needed
                if (!isset($tally[$taskId])) {
                    $tally[$taskId] = [];
                }
                if (!isset($tally[$taskId][$modelTier])) {
                    $tally[$taskId][$modelTier] = [];
                }
                if (!isset($tally[$taskId][$modelTier][$disposition])) {
                    $tally[$taskId][$modelTier][$disposition] = 0;
                }

                // Increment count
                $tally[$taskId][$modelTier][$disposition]++;
            }
        } finally {
            fclose($handle);
        }

        return $tally;
    }
}
