<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\CrashContext;
use LlmDispatch\Runner\Execution\CrashDumper;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use PHPUnit\Framework\TestCase;

final class CrashDumperTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/crash_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->tmpDir);
    }

    public function testWritesCrashDumpWithExpectedShape(): void
    {
        $state = State::empty()->withRemainingRuns([
            new Run('run-48', 'task-a', 'sonnet', 1),
        ]);

        $context = new CrashContext(
            abortedAt: '2026-04-24T03:14:22Z',
            reason: '5 consecutive unexpected errors',
            runsCompletedBeforeAbort: 47,
            runsRemaining: 1,
            errors: [
                [
                    'run_id' => 'run-48',
                    'task_id' => 'task-a',
                    'model_tier' => 'sonnet',
                    'model_id_requested' => 'claude-sonnet-4-6',
                    'iteration' => 1,
                    'timestamp' => '2026-04-24T03:12:05Z',
                    'category' => 'claude_cli_malformed_json',
                    'exit_code' => 0,
                    'stdout_tail' => 'garbage',
                    'stderr_tail' => '',
                    'claude_command' => 'claude -p ...',
                ],
            ],
            stateSnapshot: $state,
            environment: [
                'php_version' => '8.4.19',
                'claude_version' => '0.1.0',
                'experiment_commit_sha' => 'abc123',
                'hostname' => 'test',
                'timezone' => 'UTC',
            ],
        );

        $path = (new CrashDumper($this->tmpDir))->dump($context);

        $this->assertFileExists($path);
        $this->assertStringStartsWith($this->tmpDir . '/runner-crash-', $path);
        $this->assertStringEndsWith('.json', $path);

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertSame('2026-04-24T03:14:22Z', $data['aborted_at']);
        $this->assertSame(47, $data['runs_completed_before_abort']);
        $this->assertCount(1, $data['last_5_errors']);
        $this->assertSame('run-48', $data['last_5_errors'][0]['run_id']);
        $this->assertSame(1, count($data['state_snapshot']['remaining_runs']));
        $this->assertSame('8.4.19', $data['environment']['php_version']);
    }

    public function testCreatesOutputDirIfMissing(): void
    {
        $newDir = $this->tmpDir . '/nested';
        $state = State::empty();

        $context = new CrashContext(
            abortedAt: '2026-04-24T00:00:00Z',
            reason: 'test',
            runsCompletedBeforeAbort: 0,
            runsRemaining: 0,
            errors: [],
            stateSnapshot: $state,
            environment: [],
        );

        $path = (new CrashDumper($newDir))->dump($context);

        $this->assertFileExists($path);

        @unlink($path);
        @rmdir($newDir);
    }
}
