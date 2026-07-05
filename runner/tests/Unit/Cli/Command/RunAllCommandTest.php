<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\RunAllCommand;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Execution\ConnectivityChecker;
use LlmDispatch\Runner\Execution\ConsecutiveErrorCounter;
use LlmDispatch\Runner\Execution\CrashDumper;
use LlmDispatch\Runner\Execution\OfflineGate;
use LlmDispatch\Runner\Execution\ProgressLogger;
use LlmDispatch\Runner\Execution\ResultsTailReader;
use LlmDispatch\Runner\Execution\SwapDetector;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class RunAllCommandTest extends TestCase
{
    private string $statePath;
    private string $logPath;
    private string $crashDir;
    private string $pauseSentinelPath;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/runall_' . uniqid();
        mkdir($base, 0777, true);
        $this->statePath = $base . '/state.json';
        $this->logPath = $base . '/runner.log';
        $this->crashDir = $base;
        $this->pauseSentinelPath = $base . '/PAUSE';
    }

    private function makeRunNext(array $exitSequence): CommandInterface
    {
        return new class($exitSequence) implements CommandInterface {
            private int $index = 0;
            /** @param list<int> $exitSequence */
            public function __construct(private readonly array $exitSequence) {}
            public function run(array $args): int
            {
                $code = $this->exitSequence[$this->index] ?? 1;
                $this->index++;
                return $code;
            }
        };
    }

    private function makeOnlineChecker(bool $online = true): ConnectivityChecker
    {
        return new class($online) extends ConnectivityChecker {
            public function __construct(private readonly bool $isOnlineValue)
            {
                parent::__construct();
            }

            public function isOnline(): bool
            {
                return $this->isOnlineValue;
            }
        };
    }

    private function makeScriptedChecker(array $sequence): ConnectivityChecker
    {
        return new class($sequence) extends ConnectivityChecker {
            private int $index = 0;

            /** @param list<bool> $sequence */
            public function __construct(private readonly array $sequence)
            {
                parent::__construct();
            }

            public function isOnline(): bool
            {
                $result = $this->sequence[$this->index] ?? true;
                $this->index++;
                return $result;
            }
        };
    }

    public function testLoopsUntilQueueEmpty(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        // Each call returns 0 (success) until we run out — then return 1.
        $runNext = $this->makeRunNext([0, 0, 1]);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($this->statePath . '.nonexistent'),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }

    public function testAbortsAfterFiveConsecutiveErrors(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        // Five consecutive exit-3 returns.
        $runNext = $this->makeRunNext([3, 3, 3, 3, 3]);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($this->statePath . '.nonexistent'),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(10, $exit);

        $crashFiles = glob($this->crashDir . '/runner-crash-*.json') ?: [];
        $this->assertCount(1, $crashFiles, 'crash dump was not written');
    }

    public function testSuccessResetsErrorCounter(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        // Four errors, then one success, then three errors, then empty.
        $runNext = $this->makeRunNext([3, 3, 3, 3, 0, 3, 3, 3, 1]);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($this->statePath . '.nonexistent'),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit); // Not aborted.
    }

    public function testRespectsMaxRunsFlag(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        $runNext = $this->makeRunNext([0, 0, 0, 0, 0]); // more than 2

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($this->statePath . '.nonexistent'),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run(['--max-runs=2']);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }

    public function testHaltsOnSuspectedSilentSwap(): void
    {
        $base = dirname($this->statePath);
        $resultsPath = $base . '/results.jsonl';

        $runNext = new class($resultsPath) implements CommandInterface {
            public function __construct(private readonly string $resultsPath) {}
            public function run(array $args): int
            {
                file_put_contents(
                    $this->resultsPath,
                    json_encode([
                        'model_tier' => 'fable',
                        'model_id' => 'claude-fable-5',
                        'iterations' => [['model_id_reported' => 'claude-opus-4-8']],
                    ]) . "\n",
                    FILE_APPEND,
                );
                return 0;
            }
        };

        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($resultsPath),
            pinnedModels: ['fable' => 'claude-fable-5'],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(11, $exit);
    }

    public function testSingleMultiIterationRerouteDoesNotHalt(): void
    {
        $base = dirname($this->statePath);
        $resultsPath = $base . '/results.jsonl';

        $runNext = new class($resultsPath) implements CommandInterface {
            private int $calls = 0;
            public function __construct(private readonly string $resultsPath) {}
            public function run(array $args): int
            {
                $this->calls++;
                if ($this->calls === 1) {
                    file_put_contents(
                        $this->resultsPath,
                        json_encode([
                            'model_tier' => 'fable',
                            'model_id' => 'claude-fable-5',
                            'iterations' => [
                                ['model_id_reported' => 'claude-opus-4-8'],
                                ['model_id_reported' => 'claude-opus-4-8'],
                                ['model_id_reported' => 'claude-opus-4-8'],
                            ],
                        ]) . "\n",
                        FILE_APPEND,
                    );
                    return 0;
                }
                return 1;
            }
        };

        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($resultsPath),
            pinnedModels: ['fable' => 'claude-fable-5'],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }

    public function testStopsBeforeNextRunWhenPauseSentinelExists(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        // Stub that tracks calls and returns 0 (not 1, which would mean queue empty).
        $runNext = new class() implements CommandInterface {
            public int $calls = 0;

            public function run(array $args): int
            {
                $this->calls++;
                return 0;
            }
        };

        // Touch the sentinel file before running.
        touch($this->pauseSentinelPath);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($this->statePath . '.nonexistent'),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $this->makeOnlineChecker(true),
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $this->makeOnlineChecker(true),
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertSame(0, $runNext->calls, 'runNext should not have been called');

        $logContent = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('PAUSE', $logContent);
    }

    public function testOfflineErrorRequeuesRunAndSkipsErrorCounter(): void
    {
        $base = dirname($this->statePath);
        $resultsPath = $base . '/results.jsonl';

        $runId = 'test-run-123';

        // Setup initial state with a run that we'll move to completed then re-queue
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty()->withRemainingRuns([
            new Run($runId, 'task1', 'haiku', 1),
        ])->moveToCompleted($runId));

        // runNext: returns exit 3 (error) once, then 1 (queue empty)
        $runNext = new class($resultsPath, $runId) implements CommandInterface {
            private int $calls = 0;

            public function __construct(private readonly string $resultsPath, private readonly string $runId) {}

            public function run(array $args): int
            {
                $this->calls++;
                if ($this->calls === 1) {
                    // First call: write a result with claude_cli_is_error and return error
                    file_put_contents(
                        $this->resultsPath,
                        json_encode(['run_id' => $this->runId, 'status' => 'error', 'error_category' => 'claude_cli_is_error']) . "\n",
                        FILE_APPEND,
                    );
                    return 3;
                }
                return 1; // Queue empty
            }
        };

        // Connectivity: online for pre-dispatch, offline for post-error, then online for gate
        $connectivity = $this->makeScriptedChecker([true, false, true]);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($resultsPath),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $connectivity,
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $connectivity,
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);

        // Verify that the run was re-queued
        $finalState = $manager->load();
        $this->assertCount(1, $finalState->remainingRuns);
        $this->assertSame($runId, $finalState->remainingRuns[0]->runId);
        $this->assertNull($finalState->remainingRuns[0]->claimedAt);
        $this->assertCount(0, $finalState->completedRuns);

        // Verify no crash dump was written (error was handled gracefully)
        $crashFiles = glob($this->crashDir . '/runner-crash-*.json') ?: [];
        $this->assertCount(0, $crashFiles, 'crash dump should not have been written');
    }

    public function testGatesBeforeDispatchWhenOffline(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        $runNext = new class() implements CommandInterface {
            public int $calls = 0;

            public function run(array $args): int
            {
                $this->calls++;
                return 1; // Queue empty
            }
        };

        $sleeps = [];
        $sleep = static function(int $s) use (&$sleeps): void {
            $sleeps[] = $s;
        };

        // Connectivity: offline for pre-dispatch, offline on first gate check (triggers sleep), then online
        $connectivity = $this->makeScriptedChecker([false, false, true]);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($this->statePath . '.nonexistent'),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $connectivity,
                new ProgressLogger($this->logPath),
                '',
                $sleep,
            ),
            connectivity: $connectivity,
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);

        // Verify that runNext WAS called after waiting (not before)
        $this->assertGreaterThanOrEqual(1, $runNext->calls);

        // Verify that sleep was called at least once while offline
        $this->assertGreaterThan(0, count($sleeps));
    }

    public function testOnlineInfraErrorIsRequeuedWithoutCountingTowardBreaker(): void
    {
        $base = dirname($this->statePath);
        $resultsPath = $base . '/results.jsonl';

        $runId = 'test-run-infra-123';

        // Setup initial state with a run that we'll move to completed then re-queue
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty()->withRemainingRuns([
            new Run($runId, 'task1', 'haiku', 1),
        ])->moveToCompleted($runId));

        // runNext: returns exit 3 (error) once with claude_cli_is_error, then 1 (queue empty)
        $runNext = new class($resultsPath, $runId) implements CommandInterface {
            private int $calls = 0;

            public function __construct(private readonly string $resultsPath, private readonly string $runId) {}

            public function run(array $args): int
            {
                $this->calls++;
                if ($this->calls === 1) {
                    // First call: write a result with claude_cli_is_error and return error
                    file_put_contents(
                        $this->resultsPath,
                        json_encode(['run_id' => $this->runId, 'status' => 'error', 'error_category' => 'claude_cli_is_error']) . "\n",
                        FILE_APPEND,
                    );
                    return 3;
                }
                return 1; // Queue empty
            }
        };

        // Connectivity: always online
        $connectivity = $this->makeOnlineChecker(true);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($resultsPath),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $connectivity,
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $connectivity,
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);

        // Verify that the run was re-queued
        $finalState = $manager->load();
        $this->assertCount(1, $finalState->remainingRuns);
        $this->assertSame($runId, $finalState->remainingRuns[0]->runId);
        $this->assertNull($finalState->remainingRuns[0]->claimedAt);
        $this->assertCount(0, $finalState->completedRuns);

        // Verify no crash dump was written
        $crashFiles = glob($this->crashDir . '/runner-crash-*.json') ?: [];
        $this->assertCount(0, $crashFiles, 'crash dump should not have been written');

        // Verify re-queue message is in log
        $logContent = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('re-queued', $logContent);
    }

    public function testRequeueCapFallsThroughToBreaker(): void
    {
        $base = dirname($this->statePath);
        $resultsPath = $base . '/results.jsonl';

        $runId = 'test-run-cap-123';

        // Setup initial state with a run that we'll re-queue multiple times
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty()->withRemainingRuns([
            new Run($runId, 'task1', 'haiku', 1),
        ])->moveToCompleted($runId));

        // runNext: returns exit 3 (error) 8 times with claude_cli_is_error, then 1 (queue empty)
        // First 3 should requeue, remaining 5 should count toward breaker (breaker threshold is 5)
        $runNext = new class($resultsPath, $runId) implements CommandInterface {
            private int $calls = 0;

            public function __construct(private readonly string $resultsPath, private readonly string $runId) {}

            public function run(array $args): int
            {
                $this->calls++;
                if ($this->calls <= 8) {
                    // Write a result with claude_cli_is_error and return error
                    file_put_contents(
                        $this->resultsPath,
                        json_encode(['run_id' => $this->runId, 'status' => 'error', 'error_category' => 'claude_cli_is_error']) . "\n",
                        FILE_APPEND,
                    );
                    return 3;
                }
                return 1; // Queue empty
            }
        };

        // Connectivity: always online
        $connectivity = $this->makeOnlineChecker(true);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($resultsPath),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $connectivity,
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $connectivity,
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        // Should abort after 5 breaker-counted errors (3 requeues + 5 counted toward breaker = 8 total)
        $this->assertSame(10, $exit);

        // Verify crash dump was written
        $crashFiles = glob($this->crashDir . '/runner-crash-*.json') ?: [];
        $this->assertCount(1, $crashFiles, 'crash dump should have been written after breaker reached');

        // Verify log shows exactly 3 re-queue messages and then error counter messages
        $logContent = (string) file_get_contents($this->logPath);
        $requeueCount = substr_count($logContent, 're-queued');
        $this->assertSame(3, $requeueCount, 'should have exactly 3 re-queue messages');
        $this->assertStringContainsString('unexpected error', $logContent);
    }

    public function testNonInfraErrorStillCountsTowardBreaker(): void
    {
        $base = dirname($this->statePath);
        $resultsPath = $base . '/results.jsonl';

        $runId = 'test-run-non-infra-123';

        // Setup initial state with a run that we'll move to completed
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty()->withRemainingRuns([
            new Run($runId, 'task1', 'haiku', 1),
        ])->moveToCompleted($runId));

        // runNext: returns exit 3 (error) once with non-claude_cli_is_error category, then 1 (queue empty)
        $runNext = new class($resultsPath, $runId) implements CommandInterface {
            private int $calls = 0;

            public function __construct(private readonly string $resultsPath, private readonly string $runId) {}

            public function run(array $args): int
            {
                $this->calls++;
                if ($this->calls === 1) {
                    // First call: write a result with non-infra error and return error
                    file_put_contents(
                        $this->resultsPath,
                        json_encode(['run_id' => $this->runId, 'status' => 'error', 'error_category' => 'some_other_error']) . "\n",
                        FILE_APPEND,
                    );
                    return 3;
                }
                return 1; // Queue empty
            }
        };

        // Connectivity: always online
        $connectivity = $this->makeOnlineChecker(true);

        $cmd = new RunAllCommand(
            runNext: $runNext,
            progressLogger: new ProgressLogger($this->logPath),
            errorCounter: new ConsecutiveErrorCounter(5),
            crashDumper: new CrashDumper($this->crashDir),
            stateManager: $manager,
            environment: [],
            swapDetector: new SwapDetector(3),
            resultsTail: new ResultsTailReader($resultsPath),
            pinnedModels: [],
            pauseSentinelPath: $this->pauseSentinelPath,
            offlineGate: new OfflineGate(
                $connectivity,
                new ProgressLogger($this->logPath),
                '',
                static fn(int $s) => null,
            ),
            connectivity: $connectivity,
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);

        // Verify no re-queue happened (error still counts toward breaker)
        $logContent = (string) file_get_contents($this->logPath);
        $this->assertStringNotContainsString('re-queued', $logContent);
        $this->assertStringContainsString('unexpected error (count=1/5)', $logContent);

        // Verify no crash dump was written (only 1 error, not 5)
        $crashFiles = glob($this->crashDir . '/runner-crash-*.json') ?: [];
        $this->assertCount(0, $crashFiles, 'crash dump should not have been written for non-infra error');
    }
}
