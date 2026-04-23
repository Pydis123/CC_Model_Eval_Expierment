<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Worktree;

use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use LlmDispatch\Runner\Worktree\WorktreeManager;
use PHPUnit\Framework\TestCase;

final class WorktreeManagerTest extends TestCase
{
    public function testPrepareRunsGitWorktreeAddAndRemovesExperimentClaudeMd(): void
    {
        $capturedCommands = [];

        $executor = new class($capturedCommands) extends ProcessExecutor {
            public function __construct(public array &$commands) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->commands[] = ['cwd' => $cwd, 'cmd' => $command];
                return new ProcessResult(0, '', '');
            }
        };

        $tmpFs = $this->createTempWorktreeWithClaudeMd();

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $path = $manager->prepare('run-7', stubWorktreePath: $tmpFs['worktreePath']);

        $this->assertSame($tmpFs['worktreePath'], $path);
        $this->assertSame('/fake/repo', $executor->commands[0]['cwd']);
        $this->assertContains('git', $executor->commands[0]['cmd']);
        $this->assertContains('worktree', $executor->commands[0]['cmd']);
        $this->assertContains('add', $executor->commands[0]['cmd']);
        $this->assertContains($tmpFs['worktreePath'], $executor->commands[0]['cmd']);
        $this->assertContains('scaffold_complete', $executor->commands[0]['cmd']);

        $this->assertFileDoesNotExist($tmpFs['worktreePath'] . '/CLAUDE.md');

        $this->tearDownTempFs($tmpFs);
    }

    public function testCleanupPassedRemovesWorktree(): void
    {
        $capturedCommands = [];
        $executor = new class($capturedCommands) extends ProcessExecutor {
            public function __construct(public array &$commands) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->commands[] = $command;
                return new ProcessResult(0, '', '');
            }
        };

        $tmpFs = $this->createTempWorktreeWithClaudeMd();

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $manager->cleanup('run-7', $tmpFs['worktreePath'], passed: true);

        $found = false;
        foreach ($executor->commands as $cmd) {
            if (in_array('worktree', $cmd, true) && in_array('remove', $cmd, true)) {
                $found = true;
                $this->assertContains('--force', $cmd);
            }
        }
        $this->assertTrue($found, 'git worktree remove was not called');

        $this->tearDownTempFs($tmpFs);
    }

    public function testCleanupFailedMovesWorktreeToFailedDir(): void
    {
        $executor = new class extends ProcessExecutor {
            public function exec(string $cwd, array $command): ProcessResult
            {
                return new ProcessResult(0, '', '');
            }
        };

        $tmpFs = $this->createTempWorktreeWithClaudeMd();

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $manager->cleanup('run-7', $tmpFs['worktreePath'], passed: false);

        $this->assertDirectoryExists($tmpFs['failedDir'] . '/run-7');

        $this->tearDownTempFs($tmpFs);
    }

    public function testResolveWorktreePathProducesStableName(): void
    {
        $executor = new class extends ProcessExecutor {
            public function exec(string $cwd, array $command): ProcessResult { return new ProcessResult(0, '', ''); }
        };

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: '/tmp',
            failedDir: '/tmp/failed',
            baseRef: 'scaffold_complete',
        );

        $path = $manager->resolveWorktreePath('run-42');

        $this->assertSame('/tmp/llm-disp-run-42', $path);
    }

    /**
     * @return array{worktreePath: string, failedDir: string, base: string}
     */
    private function createTempWorktreeWithClaudeMd(): array
    {
        $base = sys_get_temp_dir() . '/wm_test_' . uniqid();
        $worktreePath = $base . '/llm-disp-run-7';
        $failedDir = $base . '/failed';
        mkdir($worktreePath . '/mock-project', 0777, true);
        mkdir($failedDir, 0777, true);
        file_put_contents($worktreePath . '/CLAUDE.md', 'experiment root - should be removed');
        file_put_contents($worktreePath . '/mock-project/CLAUDE.md', 'mock-project - should stay');
        return ['worktreePath' => $worktreePath, 'failedDir' => $failedDir, 'base' => $base];
    }

    /**
     * @param array{base: string} $tmpFs
     */
    private function tearDownTempFs(array $tmpFs): void
    {
        $this->rrm($tmpFs['base']);
    }

    private function rrm(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        foreach (scandir($path) ?: [] as $e) {
            if ($e === '.' || $e === '..') continue;
            $this->rrm($path . '/' . $e);
        }
        @rmdir($path);
    }
}
