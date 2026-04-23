<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;

final class FileExistsCheck implements CheckInterface
{
    /**
     * @param list<string> $paths  Paths relative to the worktree root.
     */
    public function __construct(private readonly array $paths) {}

    public function run(string $worktreePath): CheckResult
    {
        $missing = [];
        foreach ($this->paths as $path) {
            $full = $worktreePath . '/' . $path;
            if (!is_file($full)) {
                $missing[] = $path;
            }
        }

        return new CheckResult(
            type: 'file_exists',
            passed: $missing === [],
            details: ['missing' => $missing, 'paths' => $this->paths],
        );
    }
}
