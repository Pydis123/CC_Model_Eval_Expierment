<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Worktree;

use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use RuntimeException;

/**
 * Export-based worktree isolation for Phase 2 runs.
 *
 * Unlike WorktreeManager (which uses `git worktree add`, sharing the
 * experiment repo's object database), this class exports mock-project/
 * via `git archive` into a fresh, standalone git repository. A linked
 * worktree lets a subagent recover committed ground truth via
 * `git show <ref>:path`; a fresh repo with no shared ODB cannot.
 */
class ExportWorktreeManager extends WorktreeManager
{
    private const GIT_IDENTITY = ['-c', 'user.name=runner', '-c', 'user.email=runner@local'];

    public function __construct(
        private readonly ProcessExecutor $executor,
        private readonly string $repoRoot,
        string $worktreeBaseDir,
        private readonly string $failedDir,
        private readonly string $fixturesDir,
    ) {
        parent::__construct($executor, $repoRoot, $worktreeBaseDir, $failedDir, 'baseline');
    }

    public function prepareExport(string $runId, string $exportRef, string $taskId): string
    {
        $path = $this->resolveWorktreePath($runId);
        if (is_dir($path)) {
            $this->removeRecursive($path);
        }
        mkdir($path, 0777, true);

        $this->mustExec($this->repoRoot, [
            'git', 'archive', '--format=tar',
            '--output=' . $path . '/.export.tar', $exportRef, 'mock-project',
        ]);
        $this->mustExec($path, ['tar', '-xf', '.export.tar']);
        if (is_file($path . '/.export.tar')) {
            unlink($path . '/.export.tar');
        }

        $this->mustExec($path, ['git', 'init']);
        $this->mustExec($path, ['git', 'add', '-A']);
        $this->mustExec($path, [
            'git', ...self::GIT_IDENTITY, 'commit', '-m', 'baseline', '--no-gpg-sign',
        ]);
        $this->mustExec($path, ['git', 'branch', 'baseline']);

        $fixtures = $this->fixturesDir . '/' . $taskId;
        if (is_dir($fixtures)) {
            $this->copyFixtures($fixtures, $path . '/mock-project');
            if (is_file($fixtures . '/pr.patch')) {
                $this->mustExec($path, ['git', 'apply', $fixtures . '/pr.patch']);
                $this->mustExec($path, ['git', 'add', '-A']);
                $this->mustExec($path, [
                    'git', ...self::GIT_IDENTITY, 'commit', '-m', 'review-target', '--no-gpg-sign',
                ]);
                $this->mustExec($path, ['git', 'branch', 'review-target']);
            }
        }

        $this->mustExec($path . '/mock-project', [
            'composer', 'install', '--no-interaction', '--no-progress', '--prefer-dist',
        ]);

        return $path;
    }

    public function cleanup(string $runId, string $worktreePath, bool $passed): void
    {
        if ($passed) {
            $this->removeRecursive($worktreePath);
            return;
        }

        if (!is_dir($this->failedDir)) {
            mkdir($this->failedDir, 0777, true);
        }

        $dest = $this->failedDir . '/' . $runId;
        if (is_dir($worktreePath)) {
            rename($worktreePath, $dest);
        } else {
            mkdir($dest, 0777, true);
        }
    }

    /**
     * Copies every file except pr.patch from $fixturesPath into $destPath,
     * preserving relative directory structure, then commits the copied
     * files as `fixtures` only when at least one file was copied — so
     * e.g. PLAN.md is present in the run but does not pollute the
     * agent's diff when no fixture files were copied.
     */
    private function copyFixtures(string $fixturesPath, string $destPath): void
    {
        $copiedCount = 0;
        foreach ($this->listFixtureFiles($fixturesPath) as $relativePath) {
            if ($relativePath === 'pr.patch') {
                continue;
            }

            $destFile = $destPath . '/' . $relativePath;
            $destDir = dirname($destFile);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            copy($fixturesPath . '/' . $relativePath, $destFile);
            $copiedCount++;
        }

        if ($copiedCount > 0) {
            $this->mustExec($destPath, ['git', 'add', '-A']);
            $this->mustExec($destPath, [
                'git', ...self::GIT_IDENTITY, 'commit', '-m', 'fixtures', '--no-gpg-sign',
            ]);
        }
    }

    /**
     * @return list<string> Paths relative to $fixturesPath.
     */
    private function listFixtureFiles(string $fixturesPath): array
    {
        $files = [];
        $stack = [''];
        while ($stack !== []) {
            $relativeDir = array_pop($stack);
            $absoluteDir = $relativeDir === '' ? $fixturesPath : $fixturesPath . '/' . $relativeDir;
            foreach (scandir($absoluteDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $relativeEntry = $relativeDir === '' ? $entry : $relativeDir . '/' . $entry;
                if (is_dir($absoluteDir . '/' . $entry)) {
                    $stack[] = $relativeEntry;
                    continue;
                }
                $files[] = $relativeEntry;
            }
        }

        return $files;
    }

    /**
     * @param list<string> $command
     */
    private function mustExec(string $cwd, array $command): ProcessResult
    {
        $result = $this->executor->exec($cwd, $command);
        if ($result->exitCode !== 0) {
            throw new RuntimeException(sprintf(
                '%s failed: %s',
                implode(' ', $command),
                $result->stderr,
            ));
        }

        return $result;
    }
}
