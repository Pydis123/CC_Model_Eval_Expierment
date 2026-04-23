<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Support\ProcessExecutor;

final class DiffSizeLimitCheck implements CheckInterface
{
    private readonly ProcessExecutor $executor;

    public function __construct(
        private readonly int $maxLines,
        private readonly string $baseRef = 'scaffold_complete',
        ?ProcessExecutor $executor = null,
    ) {
        $this->executor = $executor ?? new ProcessExecutor();
    }

    public function run(string $worktreePath): CheckResult
    {
        $res = $this->executor->exec($worktreePath, ['git', 'diff', '--numstat', $this->baseRef]);

        if ($res->exitCode !== 0) {
            return new CheckResult(
                type: 'diff_size_limit',
                passed: false,
                details: [
                    'max_lines' => $this->maxLines,
                    'error' => trim($res->stderr),
                ],
            );
        }

        $total = 0;
        $perFile = [];
        foreach (explode("\n", trim($res->stdout)) as $line) {
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 3);
            if ($parts === false || count($parts) < 3) {
                continue;
            }
            [$added, $removed, $file] = $parts;
            if ($added === '-' || $removed === '-') {
                continue;
            }
            $addInt = (int) $added;
            $remInt = (int) $removed;
            $total += $addInt + $remInt;
            $perFile[] = ['file' => $file, 'added' => $addInt, 'removed' => $remInt];
        }

        return new CheckResult(
            type: 'diff_size_limit',
            passed: $total <= $this->maxLines,
            details: [
                'max_lines' => $this->maxLines,
                'actual_lines' => $total,
                'per_file' => $perFile,
            ],
        );
    }
}
