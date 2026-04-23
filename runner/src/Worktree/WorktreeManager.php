<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Worktree;

use LlmDispatch\Runner\Support\ProcessExecutor;
use RuntimeException;

final class WorktreeManager
{
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

        $result = $this->executor->exec($this->repoRoot, [
            'git', 'worktree', 'add', $path, $this->baseRef,
        ]);

        if ($result->exitCode !== 0) {
            throw new RuntimeException(
                sprintf('git worktree add failed: %s', $result->stderr),
            );
        }

        $experimentClaudeMd = $path . '/CLAUDE.md';
        if (is_file($experimentClaudeMd)) {
            @unlink($experimentClaudeMd);
        }

        return $path;
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
