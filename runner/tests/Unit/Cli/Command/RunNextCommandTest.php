<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\RunNextCommand;
use LlmDispatch\Runner\Dispatch\ClaudeCli;
use LlmDispatch\Runner\Dispatch\ClaudeCliResponse;
use LlmDispatch\Runner\Dispatch\DispatchEnvelopeBuilder;
use LlmDispatch\Runner\Dispatch\FailedChecksSummarizer;
use LlmDispatch\Runner\Dispatch\RateLimitInfo;
use LlmDispatch\Runner\Dispatch\RateLimitWaiter;
use LlmDispatch\Runner\Dispatch\RunCoordinator;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\EvaluationResult;
use LlmDispatch\Runner\Evaluator\EvaluatorInterface;
use LlmDispatch\Runner\Execution\TaskPromptLoader;
use LlmDispatch\Runner\Logging\ResultsLogger;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use LlmDispatch\Runner\Worktree\WorktreeManager;
use PHPUnit\Framework\TestCase;

final class RunNextCommandTest extends TestCase
{
    private string $statePath;
    private string $resultsPath;
    private string $tasksDir;
    private string $worktreeBase;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/runnext_' . uniqid();
        mkdir($base, 0777, true);
        mkdir($base . '/tasks', 0777, true);
        mkdir($base . '/worktrees', 0777, true);
        $this->statePath = $base . '/state.json';
        $this->resultsPath = $base . '/results.jsonl';
        $this->tasksDir = $base . '/tasks';
        $this->worktreeBase = $base . '/worktrees';

        file_put_contents($this->tasksDir . '/task-a.json', json_encode([
            'id' => 'task-a',
            'prompt_file' => 'task-a.prompt.md',
            'max_iterations' => 3,
            'max_wall_clock_s' => 900,
            'success_criteria' => [['type' => 'phpunit']],
        ]));
        file_put_contents($this->tasksDir . '/task-a.prompt.md', 'Do it.');
    }

    protected function tearDown(): void
    {
        // best-effort cleanup — directories may contain worktree leftovers
    }

    private function seedState(): StateManager
    {
        $state = State::empty()
            ->withRemainingRuns([new Run('run-1', 'task-a', 'haiku', 1)])
            ->withPinnedModels(['haiku' => 'claude-haiku-4-5-20251001']);
        $manager = new StateManager($this->statePath);
        $manager->save($state);
        return $manager;
    }

    private function passingResponse(): ClaudeCliResponse
    {
        return new ClaudeCliResponse(
            isError: false,
            resultText: 'ok',
            modelIdReported: 'claude-haiku-4-5-20251001',
            inputTokens: 100, outputTokens: 50, durationMs: 5000,
            stopReason: 'end_turn', costUsd: 0.01,
            rateLimit: new RateLimitInfo('allowed', null),
            rawStdout: '', rawStderr: '', exitCode: 0,
        );
    }

    private function passingEvaluator(): EvaluatorInterface
    {
        return new class implements EvaluatorInterface {
            public function evaluate(array $taskDef, string $worktreePath): EvaluationResult
            {
                return new EvaluationResult([new CheckResult('phpunit', true, [])], 0.5);
            }
        };
    }

    private function stubCli(ClaudeCliResponse $r): ClaudeCli
    {
        return new class($r) implements ClaudeCli {
            public function __construct(private readonly ClaudeCliResponse $r) {}
            public function dispatch(string $prompt, string $modelId, string $cwd, array $allowedTools): ClaudeCliResponse
            { return $this->r; }
        };
    }

    private function stubExecutor(): ProcessExecutor
    {
        // Make worktree "git worktree add" a no-op that doesn't actually invoke git.
        // We pass stubWorktreePath in prepare() to bypass actual git calls.
        return new class extends ProcessExecutor {
            public function exec(string $cwd, array $command): ProcessResult { return new ProcessResult(0, '', ''); }
        };
    }

    private function makeCommand(StateManager $state): RunNextCommand
    {
        $executor = $this->stubExecutor();

        // Create the stub worktree dir so WorktreeManager's CLAUDE.md unlink is a no-op.
        $stubPath = $this->worktreeBase . '/llm-disp-run-1';
        if (!is_dir($stubPath . '/mock-project')) {
            mkdir($stubPath . '/mock-project', 0777, true);
        }

        $coordinator = new RunCoordinator(
            cli: $this->stubCli($this->passingResponse()),
            evaluator: $this->passingEvaluator(),
            envelopeBuilder: new DispatchEnvelopeBuilder(),
            failedChecksSummarizer: new FailedChecksSummarizer(),
            rateLimitWaiter: new RateLimitWaiter(0),
            sleeper: fn(int $s) => null,
            now: fn() => 3_000_000_000,
        );

        return new RunNextCommand(
            stateManager: $state,
            resultsLogger: new ResultsLogger($this->resultsPath),
            taskPromptLoader: new TaskPromptLoader($this->tasksDir),
            worktreeManager: new WorktreeManager(
                $executor,
                '/fake/repo',
                $this->worktreeBase,
                $this->worktreeBase . '/failed',
                'scaffold_complete',
            ),
            coordinator: $coordinator,
            allowedTools: ['Bash', 'Edit', 'Read', 'Write', 'Glob', 'Grep'],
            now: fn() => '2026-04-23T14:00:00Z',
        );
    }

    public function testHappyPathWritesResultRowAndMovesStateToCompleted(): void
    {
        $state = $this->seedState();

        ob_start();
        $exit = $this->makeCommand($state)->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->resultsPath);
        $line = trim((string) file_get_contents($this->resultsPath));
        $row = json_decode($line, true);
        $this->assertSame('run-1', $row['run_id']);
        $this->assertSame('passed', $row['outcome']);
        $this->assertSame('claude-haiku-4-5-20251001', $row['model_id']);
        $this->assertSame(100, $row['tokens_subagent_in']);
        $this->assertSame(50, $row['tokens_subagent_out']);

        $reloaded = $state->load();
        $this->assertCount(0, $reloaded->remainingRuns);
        $this->assertCount(1, $reloaded->completedRuns);
    }

    public function testExitOneWhenQueueEmpty(): void
    {
        $manager = new StateManager($this->statePath);
        $manager->save(State::empty());

        ob_start();
        $exit = $this->makeCommand($manager)->run([]);
        ob_end_clean();

        $this->assertSame(1, $exit);
    }
}
