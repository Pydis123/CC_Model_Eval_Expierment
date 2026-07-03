<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Worktree;

use LlmDispatch\Runner\Support\ProcessExecutor;
use RuntimeException;

class WorktreeManager
{
    private const ALLOWED_ENTRIES = ['.', '..', '.git', 'mock-project'];

    public function __construct(
        private readonly ProcessExecutor $executor,
        private readonly string $repoRoot,
        private readonly string $worktreeBaseDir,
        private readonly string $failedDir,
        private readonly string $baseRef,
    ) {}

    public function resolveWorktreePath(string $runId): string
    {
        return $this->worktreeBaseDir . '/llm-disp-' . $runId;
    }

    public function prepare(string $runId, ?string $stubWorktreePath = null): string
    {
        $path = $stubWorktreePath ?? $this->resolveWorktreePath($runId);

        // A leftover worktree from an interrupted run makes `git worktree add` fail.
        if (is_dir($path)) {
            $this->executor->exec($this->repoRoot, [
                'git', 'worktree', 'remove', '--force', $path,
            ]);
            $this->executor->exec($this->repoRoot, [
                'git', 'worktree', 'prune',
            ]);
            // @phpstan-ignore if.alwaysTrue (the exec above removes the dir as a side effect)
            if (is_dir($path)) {
                $this->removeRecursive($path);
            }
        }

        $result = $this->executor->exec($this->repoRoot, [
            'git', 'worktree', 'add', $path, $this->baseRef,
        ]);

        if ($result->exitCode !== 0) {
            throw new RuntimeException(
                sprintf('git worktree add failed: %s', $result->stderr),
            );
        }

        foreach (scandir($path) ?: [] as $entry) {
            if (in_array($entry, self::ALLOWED_ENTRIES, true)) {
                continue;
            }
            $this->removeRecursive($path . '/' . $entry);
        }

        $leftover = [];
        foreach (scandir($path) ?: [] as $entry) {
            if (in_array($entry, self::ALLOWED_ENTRIES, true)) {
                continue;
            }
            $leftover[] = $entry;
        }

        if ($leftover !== []) {
            throw new RuntimeException(
                sprintf('Worktree prune incomplete; unexpected entries remain: %s', implode(', ', $leftover)),
            );
        }

        $composerResult = $this->executor->exec($path . '/mock-project', [
            'composer', 'install', '--no-interaction', '--no-progress', '--prefer-dist',
        ]);

        if ($composerResult->exitCode !== 0) {
            throw new RuntimeException(
                sprintf('composer install failed: %s', $composerResult->stderr),
            );
        }

        return $path;
    }

    protected function removeRecursive(string $target): void
    {
        if (is_link($target) || is_file($target)) {
            @unlink($target);
            return;
        }
        if (is_dir($target)) {
            foreach (scandir($target) ?: [] as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }
                $this->removeRecursive($target . '/' . $child);
            }
            @rmdir($target);
        }
    }

    public function cleanup(string $runId, string $worktreePath, bool $passed): void
    {
        if ($passed) {
            $this->executor->exec($this->repoRoot, [
                'git', 'worktree', 'remove', '--force', $worktreePath,
            ]);
            return;
        }

        $this->executor->exec($this->repoRoot, [
            'git', 'worktree', 'remove', '--force', $worktreePath,
        ]);

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
}
