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
use LlmDispatch\Runner\Judge\JudgeClient;
use LlmDispatch\Runner\Logging\ResultsLogger;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use LlmDispatch\Runner\Worktree\ExportWorktreeManager;
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

    private function seedStateForTask(string $taskId): StateManager
    {
        $state = State::empty()
            ->withRemainingRuns([new Run('run-1', $taskId, 'haiku', 1)])
            ->withPinnedModels(['haiku' => 'claude-haiku-4-5-20251001']);
        $manager = new StateManager($this->statePath);
        $manager->save($state);
        return $manager;
    }

    private function writeExportTask(): void
    {
        file_put_contents($this->tasksDir . '/task-export.json', json_encode([
            'id' => 'task-export',
            'prompt_file' => 'task-export.prompt.md',
            'max_iterations' => 3,
            'max_wall_clock_s' => 900,
            'success_criteria' => [['type' => 'phpunit']],
            'export_ref' => 'phase2-audit-target',
        ]));
        file_put_contents($this->tasksDir . '/task-export.prompt.md', 'Do it.');
    }

    /** @param list<string> $texts */
    private function judgeFakeCli(array $texts): ClaudeCli
    {
        return new class($texts) implements ClaudeCli {
            public int $calls = 0;

            /** @param list<string> $texts */
            public function __construct(private array $texts) {}

            public function dispatch(string $prompt, string $modelId, string $cwd, array $allowedTools): ClaudeCliResponse
            {
                $text = $this->texts[$this->calls] ?? '';
                $this->calls++;
                return new ClaudeCliResponse(
                    isError: false, resultText: $text, modelIdReported: $modelId,
                    inputTokens: 1, outputTokens: 1, durationMs: 1, stopReason: 'end_turn',
                    costUsd: 0.0, rateLimit: new RateLimitInfo('ok', null),
                    rawStdout: '', rawStderr: '', exitCode: 0,
                );
            }
        };
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
        // Make worktree "git worktree add" a no-op that doesn't actually invoke git,
        // but recreate the directory structure to avoid scandir warnings.
        return new class extends ProcessExecutor {
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                // When git worktree add is called (stubbed), recreate the directory
                if (count($command) >= 3 && $command[0] === 'git' && $command[1] === 'worktree' && $command[2] === 'add') {
                    $path = $command[3] ?? null;
                    if ($path && is_string($path)) {
                        @mkdir($path . '/mock-project', 0777, true);
                    }
                }
                return new ProcessResult(0, '', '');
            }
        };
    }

    private function makeCommand(StateManager $state, string $claudeVersion = ''): RunNextCommand
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
            claudeVersion: $claudeVersion,
        );
    }

    /**
     * Build a RunNextCommand wired with a capturing EvaluatorInterface that records
     * the worktreePath the coordinator passes to evaluate().
     *
     * The coordinator passes its own $worktreePath parameter straight to the evaluator,
     * so whatever RunNextCommand hands to execute() is what the evaluator sees.
     * This lets us assert that RunNextCommand forwards the OUTER path, not the inner one.
     */
    private function makeCommandWithCapturingEvaluator(StateManager $state, ?string &$capturedEvalPath): RunNextCommand
    {
        $executor = $this->stubExecutor();

        $stubPath = $this->worktreeBase . '/llm-disp-run-1';
        if (!is_dir($stubPath . '/mock-project')) {
            mkdir($stubPath . '/mock-project', 0777, true);
        }

        $capturedEvalPath = null;

        $capturingEvaluator = new class($capturedEvalPath) implements EvaluatorInterface {
            public function __construct(private ?string &$capturedEvalPath) {}
            public function evaluate(array $taskDef, string $worktreePath): EvaluationResult
            {
                $this->capturedEvalPath ??= $worktreePath;
                return new EvaluationResult([new CheckResult('stub', true, [])], 1.0);
            }
        };

        $coordinator = new RunCoordinator(
            cli: $this->stubCli($this->passingResponse()),
            evaluator: $capturingEvaluator,
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
            allowedTools: ['Bash'],
            now: fn() => '2026-04-23T14:00:00Z',
        );
    }

    /**
     * Regression: RunNextCommand must pass the outer worktree path (not the
     * inner mock-project subdirectory) to RunCoordinator::execute().
     * Previously $subagentCwd = $worktreePath . '/mock-project' was passed,
     * causing Evaluator checks to double-append /mock-project and crash.
     *
     * We assert by capturing the path the Evaluator receives — the coordinator
     * forwards its $worktreePath argument directly to evaluate(), so this
     * verifies what RunNextCommand handed to execute().
     */
    public function testCoordinatorReceivesOuterWorktreePathNotInnerSubdirectory(): void
    {
        $state = $this->seedState();
        $capturedEvalPath = null;

        ob_start();
        $exit = $this->makeCommandWithCapturingEvaluator($state, $capturedEvalPath)->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertNotNull($capturedEvalPath, 'Evaluator::evaluate() must have been called');

        $expectedOuterPath = $this->worktreeBase . '/llm-disp-run-1';

        // Evaluator must receive the outer path (it appends /mock-project internally).
        $this->assertSame($expectedOuterPath, $capturedEvalPath, 'Evaluator must receive the outer worktree path from RunNextCommand');

        // Regression guard: double-append must not occur.
        $this->assertStringEndsNotWith('/mock-project', $capturedEvalPath, 'Outer path must not include /mock-project suffix — that would cause double-append in Checks');
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
        $this->assertSame('completed', $row['dispatch_disposition']);
        $this->assertSame('ok', $row['iterations'][0]['result_text']);

        $reloaded = $state->load();
        $this->assertCount(0, $reloaded->remainingRuns);
        $this->assertCount(1, $reloaded->completedRuns);
    }

    public function testRowRecordsCliVersionAndDisposition(): void
    {
        $state = $this->seedState();
        ob_start();
        $this->makeCommand($state, '1.2.3 (Claude Code)')->run([]);
        ob_end_clean();
        $row = json_decode(trim((string) file_get_contents($this->resultsPath)), true);
        $this->assertSame('1.2.3 (Claude Code)', $row['claude_cli_version']);
        $this->assertSame('completed', $row['dispatch_disposition']);
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

    public function testUsesExportManagerWhenTaskHasExportRef(): void
    {
        $this->writeExportTask();
        $state = $this->seedStateForTask('task-export');

        $classicExecutor = new RecordingProcessExecutor();
        $classicManager = new WorktreeManager(
            $classicExecutor,
            '/fake/repo',
            $this->worktreeBase,
            $this->worktreeBase . '/failed',
            'scaffold_complete',
        );

        $exportManager = new RecordingExportWorktreeManager(
            new RecordingProcessExecutor(),
            $this->worktreeBase,
            $this->worktreeBase . '/failed',
        );

        $coordinator = new RunCoordinator(
            cli: $this->stubCli($this->passingResponse()),
            evaluator: $this->passingEvaluator(),
            envelopeBuilder: new DispatchEnvelopeBuilder(),
            failedChecksSummarizer: new FailedChecksSummarizer(),
            rateLimitWaiter: new RateLimitWaiter(0),
            sleeper: fn(int $s) => null,
            now: fn() => 3_000_000_000,
        );

        $command = new RunNextCommand(
            stateManager: $state,
            resultsLogger: new ResultsLogger($this->resultsPath),
            taskPromptLoader: new TaskPromptLoader($this->tasksDir),
            worktreeManager: $classicManager,
            coordinator: $coordinator,
            allowedTools: ['Bash'],
            now: fn() => '2026-04-23T14:00:00Z',
            exportWorktreeManager: $exportManager,
        );

        ob_start();
        $exit = $command->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertCount(1, $exportManager->exportCalls);
        $this->assertSame(['run-1', 'phase2-audit-target', 'task-export'], $exportManager->exportCalls[0]);
        $this->assertSame([], $classicExecutor->calls, 'classic WorktreeManager must not be invoked when export isolation applies');
    }

    public function testErrorsWhenExportRefButNoExportManager(): void
    {
        $this->writeExportTask();
        $state = $this->seedStateForTask('task-export');

        ob_start();
        $exit = $this->makeCommand($state)->run([]);
        ob_end_clean();

        $this->assertSame(4, $exit);
        $this->assertFileDoesNotExist($this->resultsPath);
    }

    public function testRowCarriesMetricsFromEvaluation(): void
    {
        $state = $this->seedState();

        $metricsEvaluator = new class implements EvaluatorInterface {
            public function evaluate(array $taskDef, string $worktreePath): EvaluationResult
            {
                return new EvaluationResult([new CheckResult('findings', true, ['metrics' => ['recall' => 0.75]])], 0.5);
            }
        };

        $executor = $this->stubExecutor();
        $stubPath = $this->worktreeBase . '/llm-disp-run-1';
        if (!is_dir($stubPath . '/mock-project')) {
            mkdir($stubPath . '/mock-project', 0777, true);
        }

        $coordinator = new RunCoordinator(
            cli: $this->stubCli($this->passingResponse()),
            evaluator: $metricsEvaluator,
            envelopeBuilder: new DispatchEnvelopeBuilder(),
            failedChecksSummarizer: new FailedChecksSummarizer(),
            rateLimitWaiter: new RateLimitWaiter(0),
            sleeper: fn(int $s) => null,
            now: fn() => 3_000_000_000,
        );

        $command = new RunNextCommand(
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
            allowedTools: ['Bash'],
            now: fn() => '2026-04-23T14:00:00Z',
        );

        ob_start();
        $command->run([]);
        ob_end_clean();

        $row = json_decode(trim((string) file_get_contents($this->resultsPath)), true);
        $this->assertSame(0.75, $row['metrics']['recall']);
    }

    public function testArtifactMissingWithJudgeRefusalVerdict(): void
    {
        $state = $this->seedState();

        $artifactMissingEvaluator = new class implements EvaluatorInterface {
            public function evaluate(array $taskDef, string $worktreePath): EvaluationResult
            {
                return new EvaluationResult([new CheckResult('findings', false, ['artifact_missing' => true])], 0.1);
            }
        };

        $executor = $this->stubExecutor();
        $stubPath = $this->worktreeBase . '/llm-disp-run-1';
        if (!is_dir($stubPath . '/mock-project')) {
            mkdir($stubPath . '/mock-project', 0777, true);
        }

        $coordinator = new RunCoordinator(
            cli: $this->stubCli($this->passingResponse()),
            evaluator: $artifactMissingEvaluator,
            envelopeBuilder: new DispatchEnvelopeBuilder(),
            failedChecksSummarizer: new FailedChecksSummarizer(),
            rateLimitWaiter: new RateLimitWaiter(0),
            sleeper: fn(int $s) => null,
            now: fn() => 3_000_000_000,
        );

        $judgeClient = new JudgeClient(
            $this->judgeFakeCli(['{"verdict": "refusal"}']),
            'claude-opus-4-8',
            '/tmp',
        );

        $command = new RunNextCommand(
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
            allowedTools: ['Bash'],
            now: fn() => '2026-04-23T14:00:00Z',
            judgeClient: $judgeClient,
        );

        ob_start();
        $command->run([]);
        ob_end_clean();

        $row = json_decode(trim((string) file_get_contents($this->resultsPath)), true);
        $this->assertSame('refused_in_band', $row['dispatch_disposition']);
    }
}

/**
 * Records every command handed to exec() so tests can assert the classic
 * WorktreeManager was never invoked when export isolation took over.
 */
final class RecordingProcessExecutor extends ProcessExecutor
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @param array<string, string>|null $env */
    public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
    {
        $this->calls[] = $command;
        return new ProcessResult(0, '', '');
    }
}

/**
 * Test double for ExportWorktreeManager: records prepareExport() calls and
 * returns a real temp directory instead of shelling out to git/tar/composer.
 */
final class RecordingExportWorktreeManager extends ExportWorktreeManager
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $exportCalls = [];

    private readonly string $tempDir;

    public function __construct(ProcessExecutor $executor, string $worktreeBaseDir, string $failedDir)
    {
        parent::__construct($executor, '/fake/repo', $worktreeBaseDir, $failedDir, $worktreeBaseDir . '/fixtures');
        $this->tempDir = $worktreeBaseDir . '/export-recorded';
    }

    public function prepareExport(string $runId, string $exportRef, string $taskId): string
    {
        $this->exportCalls[] = [$runId, $exportRef, $taskId];
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        return $this->tempDir;
    }

    public function cleanup(string $runId, string $worktreePath, bool $passed): void
    {
        // no-op: nothing was actually shelled out to clean up.
    }
}
