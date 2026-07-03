<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\RunAllCommand;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Execution\ConsecutiveErrorCounter;
use LlmDispatch\Runner\Execution\CrashDumper;
use LlmDispatch\Runner\Execution\ProgressLogger;
use LlmDispatch\Runner\Execution\ResultsTailReader;
use LlmDispatch\Runner\Execution\SwapDetector;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class RunAllCommandTest extends TestCase
{
    private string $statePath;
    private string $logPath;
    private string $crashDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/runall_' . uniqid();
        mkdir($base, 0777, true);
        $this->statePath = $base . '/state.json';
        $this->logPath = $base . '/runner.log';
        $this->crashDir = $base;
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
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }
}
