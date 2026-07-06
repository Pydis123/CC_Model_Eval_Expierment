<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Worktree;

use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use LlmDispatch\Runner\Worktree\ExportWorktreeManager;
use PHPUnit\Framework\TestCase;

final class ExportWorktreeManagerTest extends TestCase
{
    public function testExportSequenceHasNoLinkedWorktree(): void
    {
        $tmpFs = $this->createTempDirs();
        $executor = $this->recordingExecutor($commands);

        $manager = new ExportWorktreeManager(
            executor: $executor,
            repoRoot: $tmpFs['repoRoot'],
            worktreeBaseDir: $tmpFs['worktreeBaseDir'],
            failedDir: $tmpFs['failedDir'],
            fixturesDir: $tmpFs['fixturesDir'],
        );

        $manager->prepareExport('r1', 'phase2-audit-target', '102-security-audit');

        $archiveCommand = null;
        $initCommand = null;
        foreach ($commands as [$cwd, $command]) {
            if ($command[0] === 'git' && $command[1] === 'archive') {
                $archiveCommand = $command;
            }
            if ($command[0] === 'git' && $command[1] === 'init') {
                $initCommand = $command;
            }
        }

        $this->assertNotNull($archiveCommand, 'git archive command not found');
        $this->assertContains('phase2-audit-target', $archiveCommand);
        $this->assertNotNull($initCommand, 'git init command not found');

        foreach ($commands as [$cwd, $command]) {
            $this->assertNotContains('worktree', $command, 'no command should reference git worktree');
        }

        $this->tearDownTempFs($tmpFs);
    }

    public function testPrPatchFixtureCreatesReviewTargetBranch(): void
    {
        $tmpFs = $this->createTempDirs();
        mkdir($tmpFs['fixturesDir'] . '/103-code-review', 0777, true);
        file_put_contents($tmpFs['fixturesDir'] . '/103-code-review/pr.patch', "diff --git a/x b/x\n");

        $executor = $this->recordingExecutor($commands);

        $manager = new ExportWorktreeManager(
            executor: $executor,
            repoRoot: $tmpFs['repoRoot'],
            worktreeBaseDir: $tmpFs['worktreeBaseDir'],
            failedDir: $tmpFs['failedDir'],
            fixturesDir: $tmpFs['fixturesDir'],
        );

        $manager->prepareExport('r2', 'phase2-audit-target', '103-code-review');

        $hasApply = false;
        $hasReviewTargetBranch = false;
        foreach ($commands as [$cwd, $command]) {
            if ($command[0] === 'git' && $command[1] === 'apply') {
                $hasApply = true;
            }
            if ($command[0] === 'git' && $command[1] === 'branch' && in_array('review-target', $command, true)) {
                $hasReviewTargetBranch = true;
            }
        }

        $this->assertTrue($hasApply, 'expected a git apply command');
        $this->assertTrue($hasReviewTargetBranch, 'expected a git branch review-target command');

        $this->tearDownTempFs($tmpFs);
    }

    public function testCleanupOnFailureMovesToFailedDir(): void
    {
        $tmpFs = $this->createTempDirs();
        $executor = $this->recordingExecutor($commands);

        $manager = new ExportWorktreeManager(
            executor: $executor,
            repoRoot: $tmpFs['repoRoot'],
            worktreeBaseDir: $tmpFs['worktreeBaseDir'],
            failedDir: $tmpFs['failedDir'],
            fixturesDir: $tmpFs['fixturesDir'],
        );

        $path = $manager->resolveWorktreePath('r1');
        mkdir($path, 0777, true);
        file_put_contents($path . '/marker.txt', 'hi');

        $manager->cleanup('r1', $path, false);

        $this->assertDirectoryExists($tmpFs['failedDir'] . '/r1');
        $this->assertFileExists($tmpFs['failedDir'] . '/r1/marker.txt');
        $this->assertDirectoryDoesNotExist($path);

        foreach ($commands as [$cwd, $command]) {
            $this->assertNotContains('worktree', $command, 'no command should reference git worktree');
        }

        $this->tearDownTempFs($tmpFs);
    }

    /**
     * @param-out list<array{0: string, 1: list<string>}> $commands
     */
    private function recordingExecutor(?array &$commands): ProcessExecutor
    {
        $commands = [];

        return new class ($commands) extends ProcessExecutor {
            /**
             * @param list<array{0: string, 1: list<string>}> $commands
             */
            public function __construct(private array &$commands)
            {
            }

            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->commands[] = [$cwd, $command];

                return new ProcessResult(0, '', '');
            }
        };
    }

    /**
     * @return array{repoRoot: string, worktreeBaseDir: string, failedDir: string, fixturesDir: string, base: string}
     */
    private function createTempDirs(): array
    {
        $base = sys_get_temp_dir() . '/ewm_test_' . uniqid();
        $dirs = [
            'repoRoot' => $base . '/repo',
            'worktreeBaseDir' => $base . '/worktrees',
            'failedDir' => $base . '/failed',
            'fixturesDir' => $base . '/fixtures',
        ];
        foreach ($dirs as $dir) {
            mkdir($dir, 0777, true);
        }

        return $dirs + ['base' => $base];
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
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $this->rrm($path . '/' . $e);
        }
        @rmdir($path);
    }
}
