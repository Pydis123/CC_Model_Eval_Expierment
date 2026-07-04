<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use DateTimeImmutable;
use DateTimeZone;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Execution\ConnectivityChecker;
use LlmDispatch\Runner\Execution\ConsecutiveErrorCounter;
use LlmDispatch\Runner\Execution\CrashContext;
use LlmDispatch\Runner\Execution\CrashDumper;
use LlmDispatch\Runner\Execution\OfflineGate;
use LlmDispatch\Runner\Execution\ProgressLogger;
use LlmDispatch\Runner\Execution\ResultsTailReader;
use LlmDispatch\Runner\Execution\SwapDetector;
use LlmDispatch\Runner\State\StateManager;

final class RunAllCommand implements CommandInterface
{
    /**
     * @param array<string, string> $environment
     * @param array<string, string> $pinnedModels
     */
    public function __construct(
        private readonly CommandInterface $runNext,
        private readonly ProgressLogger $progressLogger,
        private readonly ConsecutiveErrorCounter $errorCounter,
        private readonly CrashDumper $crashDumper,
        private readonly StateManager $stateManager,
        private readonly array $environment,
        private readonly SwapDetector $swapDetector,
        private readonly ResultsTailReader $resultsTail,
        private readonly array $pinnedModels,
        private readonly string $pauseSentinelPath,
        private readonly OfflineGate $offlineGate,
        private readonly ConnectivityChecker $connectivity,
    ) {}

    public function run(array $args): int
    {
        $maxRuns = $this->argInt($args, '--max-runs=', PHP_INT_MAX);
        $runsExecuted = 0;

        while ($runsExecuted < $maxRuns) {
            if ($this->pauseSentinelPath !== '' && file_exists($this->pauseSentinelPath)) {
                $this->progressLogger->log('PAUSE sentinel found; stopping cleanly before next run.');
                return 0;
            }

            if (!$this->connectivity->isOnline()) {
                $this->progressLogger->log('offline before dispatch; gating until connectivity returns.');
                if (!$this->offlineGate->waitUntilOnline()) {
                    return 0; // PAUSE appeared while waiting
                }
            }

            $exit = $this->runNext->run([]);

            if ($exit === 1) {
                // Queue empty.
                $this->progressLogger->log('queue empty; done.');
                return 0;
            }

            if ($exit === 0) {
                // Registered run (passed or failed as normal outcome).
                $this->errorCounter->recordRegisteredRun();
                $this->checkForSwap();
                if ($this->swapDetector->shouldHalt()) {
                    $this->abortSwap();
                    return 11;
                }
                $runsExecuted++;
                continue;
            }

            if ($exit === 3) {
                // Unexpected error category.
                // Check if we're offline — if so, re-queue and wait instead of counting error.
                if (!$this->connectivity->isOnline()) {
                    $row = $this->resultsTail->last();
                    $runId = (string) ($row['run_id'] ?? '');
                    if ($runId !== '') {
                        $this->stateManager->save($this->stateManager->load()->requeueCompleted($runId));
                        $this->progressLogger->log(sprintf('offline after error; run %s re-queued, gating until connectivity returns.', $runId));
                    }
                    if (!$this->offlineGate->waitUntilOnline()) {
                        return 0;
                    }
                    continue;   // do NOT touch the error counter, do NOT increment $runsExecuted
                }

                $this->errorCounter->recordUnexpectedError();
                $this->progressLogger->log(
                    sprintf('unexpected error (count=%d/%d)', $this->errorCounter->count(), 5),
                );

                if ($this->errorCounter->shouldAbort()) {
                    $this->abort();
                    return 10;
                }

                $runsExecuted++;
                continue;
            }

            // Unknown non-zero exit from run-next — treat as unexpected error.
            $this->errorCounter->recordUnexpectedError();
            if ($this->errorCounter->shouldAbort()) {
                $this->abort();
                return 10;
            }
            $runsExecuted++;
        }

        return 0;
    }

    private function abort(): void
    {
        $state = $this->stateManager->load();
        $abortedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        $context = new CrashContext(
            abortedAt: $abortedAt,
            reason: '5 consecutive unexpected errors',
            runsCompletedBeforeAbort: count($state->completedRuns),
            runsRemaining: count($state->remainingRuns),
            errors: [], // Detailed error capture is deferred to RunNextCommand's log; this is best-effort.
            stateSnapshot: $state,
            environment: $this->environment,
        );

        $path = $this->crashDumper->dump($context);

        $this->progressLogger->log(
            sprintf('ABORT after 5 consecutive unexpected errors. Crash dump: %s', $path),
        );
    }

    /**
     * @param list<string> $args
     */
    private function argInt(array $args, string $prefix, int $default): int
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return (int) substr($arg, strlen($prefix));
            }
        }
        return $default;
    }

    private function checkForSwap(): void
    {
        $row = $this->resultsTail->last();
        if ($row === null) {
            return;
        }
        $tier = (string) ($row['model_tier'] ?? '');
        $expected = (string) ($this->pinnedModels[$tier] ?? '');
        if ($tier === '' || $expected === '') {
            return;
        }
        $reported = '';
        foreach ((array) ($row['iterations'] ?? []) as $it) {
            $candidate = (string) ($it['model_id_reported'] ?? '');
            if ($candidate !== '') {
                $reported = $candidate;
            }
        }
        $this->swapDetector->record($tier, $reported, $expected);
    }

    private function abortSwap(): void
    {
        $reason = (string) $this->swapDetector->haltReason();
        $this->progressLogger->log('ABORT: ' . $reason);
        $state = $this->stateManager->load();
        $abortedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $context = new CrashContext(
            abortedAt: $abortedAt,
            reason: $reason,
            runsCompletedBeforeAbort: count($state->completedRuns),
            runsRemaining: count($state->remainingRuns),
            errors: [],
            stateSnapshot: $state,
            environment: $this->environment,
        );
        $this->crashDumper->dump($context);
    }
}
