<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

use LlmDispatch\Runner\Config;
use RuntimeException;

final class Aggregator
{
    /**
     * @return array<string, array<string, CellStats>>
     */
    public function aggregate(string $jsonlPath, Config $config): array
    {
        if (!is_file($jsonlPath)) {
            throw new RuntimeException("Results file missing: {$jsonlPath}");
        }

        $handle = fopen($jsonlPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open results file: {$jsonlPath}");
        }

        // First pass: collect by run_id to handle duplicates (keep only the last)
        /** @var array<string, ResultsRow> $byRunId */
        $byRunId = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                /** @var array<string, mixed> $data */
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $row = ResultsRow::fromArray($data);
                $byRunId[$row->runId] = $row;  // Later rows overwrite earlier ones
            }
        } finally {
            fclose($handle);
        }

        // Filter out rows with dispatch_disposition="error" or "contaminated"
        $byRunId = array_filter(
            $byRunId,
            static fn(ResultsRow $row) => !in_array($row->dispatchDisposition, ['error', 'contaminated'], true)
        );

        // Second pass: group by task_id and model_tier
        /** @var array<string, array<string, list<ResultsRow>>> $grouped */
        $grouped = [];
        foreach ($byRunId as $row) {
            $grouped[$row->taskId][$row->modelTier][] = $row;
        }

        $matrix = [];
        foreach ($config->taskIds as $taskId) {
            foreach ($config->tiers as $tier) {
                $runs = $grouped[$taskId][$tier] ?? [];
                if (count($runs) !== $config->nReplicates) {
                    $actual = count($runs);
                    throw new IncompleteResultsException(
                        "Cell ({$taskId}, {$tier}) has {$actual} runs, expected {$config->nReplicates}"
                    );
                }
                $matrix[$taskId][$tier] = new CellStats($runs);
            }
        }

        return $matrix;
    }
}
