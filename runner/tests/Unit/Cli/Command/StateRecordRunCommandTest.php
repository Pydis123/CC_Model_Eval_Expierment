<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\StateRecordRunCommand;
use LlmDispatch\Runner\Logging\ResultsLogger;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StateRecordRunCommandTest extends TestCase
{
    private string $tmpStatePath;
    private string $tmpResultsPath;
    private string $tmpEvalPath;

    protected function setUp(): void
    {
        $this->tmpStatePath = sys_get_temp_dir() . '/state_' . uniqid() . '.json';
        $this->tmpResultsPath = sys_get_temp_dir() . '/results_' . uniqid() . '.jsonl';
        $this->tmpEvalPath = sys_get_temp_dir() . '/eval_' . uniqid() . '.json';

        file_put_contents($this->tmpEvalPath, json_encode([
            'outcome' => 'passed',
            'wall_clock_s' => 22.5,
            'checks' => [['type' => 'phpunit', 'passed' => true, 'wall_clock_s' => 20, 'details' => []]],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpStatePath);
        @unlink($this->tmpResultsPath);
        @unlink($this->tmpEvalPath);
    }

    public function testAppendsResultsRowAndMovesRunToCompleted(): void
    {
        $stateManager = new StateManager($this->tmpStatePath);
        $stateManager->save(State::empty()->withRemainingRuns([
            new Run('abc123', '003-n-plus-one-fix', 'sonnet', 2),
        ])->withPinnedModels([
            'haiku' => 'claude-haiku-4-5', 'sonnet' => 'claude-sonnet-4-6', 'opus' => 'claude-opus-4-7',
        ]));
        $logger = new ResultsLogger($this->tmpResultsPath);

        $command = new StateRecordRunCommand($stateManager, $logger);

        ob_start();
        $exit = $command->run([
            '--run-id=abc123',
            '--evaluator-result=' . $this->tmpEvalPath,
            '--subagent-s=780',
            '--pm-overhead-s=98',
            '--tokens-in=12300',
            '--tokens-out=3200',
            '--tokens-pm-overhead=2800',
            '--model-id=claude-sonnet-4-6',
            '--timestamp-start=2026-04-23T10:00:00Z',
            '--timestamp-end=2026-04-23T10:14:15Z',
            '--iteration={"n":1,"outcome":"failed","subagent_s":420,"evaluator_s":22}',
            '--iteration={"n":2,"outcome":"passed","subagent_s":360,"evaluator_s":23}',
        ]);
        ob_end_clean();

        $this->assertSame(0, $exit);

        $reloaded = $stateManager->load();
        $this->assertSame([], $reloaded->remainingRuns);
        $this->assertCount(1, $reloaded->completedRuns);
        $this->assertSame('abc123', $reloaded->completedRuns[0]->runId);

        $lines = array_values(array_filter(explode("\n", file_get_contents($this->tmpResultsPath))));
        $this->assertCount(1, $lines);
        $row = json_decode($lines[0], true);
        $this->assertSame('abc123', $row['run_id']);
        $this->assertSame('003-n-plus-one-fix', $row['task_id']);
        $this->assertSame('sonnet', $row['model_tier']);
        $this->assertSame('claude-sonnet-4-6', $row['model_id']);
        $this->assertSame(2, $row['n']);
        $this->assertSame('passed', $row['outcome']);
        $this->assertSame(2, $row['iterations_used']);
        $this->assertSame(780, $row['wall_clock_subagent_s']);
        $this->assertSame(12300, $row['tokens_subagent_in']);
        $this->assertCount(2, $row['iterations']);
    }

    public function testMissingOptionalArgsLoggedAsNull(): void
    {
        $stateManager = new StateManager($this->tmpStatePath);
        $stateManager->save(State::empty()->withRemainingRuns([
            new Run('abc', 't', 'haiku', 1),
        ]));
        $logger = new ResultsLogger($this->tmpResultsPath);

        $command = new StateRecordRunCommand($stateManager, $logger);

        ob_start();
        $exit = $command->run([
            '--run-id=abc',
            '--evaluator-result=' . $this->tmpEvalPath,
            '--timestamp-start=2026-04-23T10:00:00Z',
            '--timestamp-end=2026-04-23T10:01:00Z',
        ]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $row = json_decode(trim(file_get_contents($this->tmpResultsPath)), true);
        $this->assertNull($row['tokens_subagent_in']);
        $this->assertNull($row['model_id']);
        $this->assertNull($row['wall_clock_subagent_s']);
    }

    public function testFailsIfRunIdNotInRemaining(): void
    {
        $stateManager = new StateManager($this->tmpStatePath);
        $stateManager->save(State::empty());
        $logger = new ResultsLogger($this->tmpResultsPath);

        $command = new StateRecordRunCommand($stateManager, $logger);

        ob_start();
        $exit = $command->run([
            '--run-id=unknown',
            '--evaluator-result=' . $this->tmpEvalPath,
            '--timestamp-start=2026-04-23T10:00:00Z',
            '--timestamp-end=2026-04-23T10:01:00Z',
        ]);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }
}
