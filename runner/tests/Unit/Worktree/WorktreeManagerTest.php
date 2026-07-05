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
        $tmpFs = $this->createTempWorktreeWithClaudeMd();

        $executor = new class($capturedCommands, $tmpFs['worktreePath']) extends ProcessExecutor {
            public function __construct(public array &$commands, private string $worktreePath) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->commands[] = ['cwd' => $cwd, 'cmd' => $command];
                // When git worktree add is called (stubbed), recreate the directory
                if (count($command) >= 3 && $command[0] === 'git' && $command[1] === 'worktree' && $command[2] === 'add') {
                    @mkdir($this->worktreePath . '/mock-project', 0777, true);
                }
                return new ProcessResult(0, '', '');
            }
        };

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $path = $manager->prepare('run-7', stubWorktreePath: $tmpFs['worktreePath']);

        $this->assertSame($tmpFs['worktreePath'], $path);
        // Find the git worktree add command (it might not be at index 0 if cleanup commands are present)
        $addCommand = null;
        foreach ($executor->commands as $cmd) {
            if (in_array('add', $cmd['cmd'], true)) {
                $addCommand = $cmd;
                break;
            }
        }
        $this->assertNotNull($addCommand, 'git worktree add command not found');
        $this->assertSame('/fake/repo', $addCommand['cwd']);
        $this->assertContains('git', $addCommand['cmd']);
        $this->assertContains('worktree', $addCommand['cmd']);
        $this->assertContains('add', $addCommand['cmd']);
        $this->assertContains($tmpFs['worktreePath'], $addCommand['cmd']);
        $this->assertContains('scaffold_complete', $addCommand['cmd']);

        $this->assertFileDoesNotExist($tmpFs['worktreePath'] . '/CLAUDE.md');

        // Assert composer install was called with correct arguments and cwd
        $composerCall = null;
        foreach ($executor->commands as $cmd) {
            if (in_array('composer', $cmd['cmd'], true)) {
                $composerCall = $cmd;
                break;
            }
        }
        $this->assertNotNull($composerCall, 'composer install command not found');
        $this->assertSame($tmpFs['worktreePath'] . '/mock-project', $composerCall['cwd']);
        $this->assertSame(['composer', 'install', '--no-interaction', '--no-progress', '--prefer-dist'], $composerCall['cmd']);

        $this->tearDownTempFs($tmpFs);
    }

    public function testPruneLeavesOnlyMockProject(): void
    {
        $tmpFs = $this->createTempWorktreeWithExtraEntries();

        $executor = new class($tmpFs['worktreePath']) extends ProcessExecutor {
            public function __construct(private string $worktreePath) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                // When git worktree add is called (stubbed), recreate the directory
                if (count($command) >= 3 && $command[0] === 'git' && $command[1] === 'worktree' && $command[2] === 'add') {
                    @mkdir($this->worktreePath . '/mock-project', 0777, true);
                }
                return new ProcessResult(0, '', '');
            }
        };

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $manager->prepare('run-prune', stubWorktreePath: $tmpFs['worktreePath']);

        $entries = array_values(array_diff(scandir($tmpFs['worktreePath']) ?: [], ['.', '..', '.git']));
        $this->assertSame(['mock-project'], $entries);

        $this->tearDownTempFs($tmpFs);
    }

    public function testPrepareThrowsWhenPruneLeavesDisallowedEntry(): void
    {
        $tmpFs = $this->createTempWorktreeWithExtraEntries();

        $executor = new class($tmpFs['worktreePath']) extends ProcessExecutor {
            public function __construct(private string $worktreePath) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                // When git worktree add is called (stubbed), recreate the directory
                if (count($command) >= 3 && $command[0] === 'git' && $command[1] === 'worktree' && $command[2] === 'add') {
                    @mkdir($this->worktreePath . '/mock-project', 0777, true);
                    @mkdir($this->worktreePath . '/tasks', 0777, true);
                    @mkdir($this->worktreePath . '/docs', 0777, true);
                }
                return new ProcessResult(0, '', '');
            }
        };

        $manager = new class(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        ) extends WorktreeManager {
            protected function removeRecursive(string $target): void
            {
                // No-op: simulates a silently failed deletion so the
                // disallowed entries survive the prune loop.
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('prune incomplete');

        try {
            $manager->prepare('run-guard', stubWorktreePath: $tmpFs['worktreePath']);
        } finally {
            $this->tearDownTempFs($tmpFs);
        }
    }

    public function testPrepareFailsIfComposerInstallExitsNonzero(): void
    {
        $tmpFs = $this->createTempWorktreeWithClaudeMd();

        $executor = new class($tmpFs['worktreePath']) extends ProcessExecutor {
            public function __construct(private string $worktreePath) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                if ($command[0] === 'git') {
                    // When git worktree add is called (stubbed), recreate the directory
                    if (count($command) >= 3 && $command[1] === 'worktree' && $command[2] === 'add') {
                        @mkdir($this->worktreePath . '/mock-project', 0777, true);
                    }
                    return new ProcessResult(0, '', '');
                }
                // composer call fails
                return new ProcessResult(1, '', 'Package not found: some/dep');
            }
        };

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Package not found: some\/dep/');

        try {
            $manager->prepare('run-7', stubWorktreePath: $tmpFs['worktreePath']);
        } finally {
            $this->tearDownTempFs($tmpFs);
        }
    }

    public function testCleanupPassedRemovesWorktree(): void
    {
        $capturedCommands = [];
        $executor = new class($capturedCommands) extends ProcessExecutor {
            public function __construct(public array &$commands) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
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
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
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
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult { return new ProcessResult(0, '', ''); }
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

    public function testPrepareRemovesLeftoverWorktreeBeforeAdd(): void
    {
        $capturedCommands = [];
        $tmpFs = $this->createTempWorktreeWithClaudeMd();

        $executor = new class($capturedCommands, $tmpFs['worktreePath']) extends ProcessExecutor {
            public function __construct(public array &$commands, private string $worktreePath) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->commands[] = ['cwd' => $cwd, 'cmd' => $command];
                // When git worktree add is called (stubbed), recreate the directory
                // to mimic real git behavior, since the stub doesn't actually run git
                if (count($command) >= 3 && $command[0] === 'git' && $command[1] === 'worktree' && $command[2] === 'add') {
                    @mkdir($this->worktreePath . '/mock-project', 0777, true);
                }
                return new ProcessResult(0, '', '');
            }
        };

        $manager = new WorktreeManager(
            executor: $executor,
            repoRoot: '/fake/repo',
            worktreeBaseDir: dirname($tmpFs['worktreePath']),
            failedDir: $tmpFs['failedDir'],
            baseRef: 'scaffold_complete',
        );

        $path = $manager->prepare('run-7', stubWorktreePath: $tmpFs['worktreePath']);

        $this->assertSame($tmpFs['worktreePath'], $path);

        // Verify the cleanup commands are issued before add
        $this->assertGreaterThanOrEqual(4, count($executor->commands), 'Expected at least 4 commands (remove, prune, add, composer)');

        // First command: git worktree remove --force
        $this->assertSame('/fake/repo', $executor->commands[0]['cwd']);
        $this->assertContains('git', $executor->commands[0]['cmd']);
        $this->assertContains('worktree', $executor->commands[0]['cmd']);
        $this->assertContains('remove', $executor->commands[0]['cmd']);
        $this->assertContains('--force', $executor->commands[0]['cmd']);
        $this->assertContains($tmpFs['worktreePath'], $executor->commands[0]['cmd']);

        // Second command: git worktree prune
        $this->assertSame('/fake/repo', $executor->commands[1]['cwd']);
        $this->assertContains('git', $executor->commands[1]['cmd']);
        $this->assertContains('worktree', $executor->commands[1]['cmd']);
        $this->assertContains('prune', $executor->commands[1]['cmd']);

        // Third command: git worktree add
        $this->assertSame('/fake/repo', $executor->commands[2]['cwd']);
        $this->assertContains('git', $executor->commands[2]['cmd']);
        $this->assertContains('worktree', $executor->commands[2]['cmd']);
        $this->assertContains('add', $executor->commands[2]['cmd']);
        $this->assertContains($tmpFs['worktreePath'], $executor->commands[2]['cmd']);
        $this->assertContains('scaffold_complete', $executor->commands[2]['cmd']);

        // Fourth command: composer install (unchanged from existing behavior)
        $composerCall = $executor->commands[3];
        $this->assertSame($tmpFs['worktreePath'] . '/mock-project', $composerCall['cwd']);
        $this->assertSame(['composer', 'install', '--no-interaction', '--no-progress', '--prefer-dist'], $composerCall['cmd']);

        $this->tearDownTempFs($tmpFs);
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
     * @return array{worktreePath: string, failedDir: string, base: string}
     */
    private function createTempWorktreeWithExtraEntries(): array
    {
        $base = sys_get_temp_dir() . '/wm_test_' . uniqid();
        $worktreePath = $base . '/llm-disp-run-prune';
        $failedDir = $base . '/failed';
        mkdir($worktreePath . '/mock-project', 0777, true);
        mkdir($worktreePath . '/tasks', 0777, true);
        mkdir($worktreePath . '/docs', 0777, true);
        mkdir($failedDir, 0777, true);
        file_put_contents($worktreePath . '/CLAUDE.md', 'experiment root - should be removed');
        file_put_contents($worktreePath . '/mock-project/composer.json', '{}');
        file_put_contents($worktreePath . '/tasks/ground-truth.json', '{}');
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
