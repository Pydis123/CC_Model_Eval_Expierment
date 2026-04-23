<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Logging\ResultsLogger;
use LlmDispatch\Runner\State\StateManager;

final class StateRecordRunCommand implements CommandInterface
{
    public function __construct(
        private readonly StateManager $stateManager,
        private readonly ResultsLogger $resultsLogger,
    ) {}

    public function run(array $args): int
    {
        $parsed = $this->parseArgs($args);
        if ($parsed['run_id'] === null || $parsed['evaluator_result'] === null) {
            fwrite(STDERR, "Required: --run-id=, --evaluator-result=\n");
            return 2;
        }

        $state = $this->stateManager->load();
        $targetRun = null;
        foreach ($state->remainingRuns as $run) {
            if ($run->runId === $parsed['run_id']) {
                $targetRun = $run;
                break;
            }
        }
        if ($targetRun === null) {
            fwrite(STDERR, "Unknown run-id: {$parsed['run_id']}\n");
            return 2;
        }

        $evalPath = $parsed['evaluator_result'];
        if (!is_file($evalPath)) {
            fwrite(STDERR, "Evaluator result file missing: {$evalPath}\n");
            return 2;
        }
        /** @var array<string, mixed> $evalData */
        $evalData = json_decode((string) file_get_contents($evalPath), true, 512, JSON_THROW_ON_ERROR);

        $row = [
            'run_id' => $targetRun->runId,
            'task_id' => $targetRun->taskId,
            'model_tier' => $targetRun->modelTier,
            'model_id' => $parsed['model_id'],
            'n' => $targetRun->n,
            'outcome' => (string) ($evalData['outcome'] ?? 'failed'),
            'iterations_used' => count($parsed['iterations']),
            'iterations' => $parsed['iterations'],
            'tokens_subagent_in' => $parsed['tokens_in'],
            'tokens_subagent_out' => $parsed['tokens_out'],
            'tokens_pm_overhead' => $parsed['tokens_pm_overhead'],
            'wall_clock_subagent_s' => $parsed['subagent_s'],
            'wall_clock_total_s' => $this->totalSeconds($parsed['timestamp_start'], $parsed['timestamp_end']),
            'timestamp_start' => $parsed['timestamp_start'],
            'timestamp_end' => $parsed['timestamp_end'],
            'evaluator_details' => $evalData,
        ];

        $this->resultsLogger->append($row);

        $newState = $state->moveToCompleted($targetRun->runId);
        $this->stateManager->save($newState);

        echo json_encode(['recorded' => $targetRun->runId], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        return 0;
    }

    /**
     * @param list<string> $args
     * @return array{run_id: ?string, evaluator_result: ?string, subagent_s: ?int, pm_overhead_s: ?int, tokens_in: ?int, tokens_out: ?int, tokens_pm_overhead: ?int, model_id: ?string, timestamp_start: ?string, timestamp_end: ?string, iterations: list<array<string, mixed>>}
     */
    private function parseArgs(array $args): array
    {
        $out = [
            'run_id' => null,
            'evaluator_result' => null,
            'subagent_s' => null,
            'pm_overhead_s' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'tokens_pm_overhead' => null,
            'model_id' => null,
            'timestamp_start' => null,
            'timestamp_end' => null,
            'iterations' => [],
        ];

        $map = [
            '--run-id=' => 'run_id',
            '--evaluator-result=' => 'evaluator_result',
            '--model-id=' => 'model_id',
            '--timestamp-start=' => 'timestamp_start',
            '--timestamp-end=' => 'timestamp_end',
        ];
        $intMap = [
            '--subagent-s=' => 'subagent_s',
            '--pm-overhead-s=' => 'pm_overhead_s',
            '--tokens-in=' => 'tokens_in',
            '--tokens-out=' => 'tokens_out',
            '--tokens-pm-overhead=' => 'tokens_pm_overhead',
        ];

        foreach ($args as $arg) {
            foreach ($map as $prefix => $key) {
                if (str_starts_with($arg, $prefix)) {
                    $out[$key] = substr($arg, strlen($prefix));
                    continue 2;
                }
            }
            foreach ($intMap as $prefix => $key) {
                if (str_starts_with($arg, $prefix)) {
                    $out[$key] = (int) substr($arg, strlen($prefix));
                    continue 2;
                }
            }
            if (str_starts_with($arg, '--iteration=')) {
                $iter = json_decode(substr($arg, 12), true, 512, JSON_THROW_ON_ERROR);
                $out['iterations'][] = $iter;
            }
        }

        return $out;
    }

    private function totalSeconds(?string $start, ?string $end): ?int
    {
        if ($start === null || $end === null) {
            return null;
        }
        return (new \DateTimeImmutable($end))->getTimestamp() - (new \DateTimeImmutable($start))->getTimestamp();
    }
}
