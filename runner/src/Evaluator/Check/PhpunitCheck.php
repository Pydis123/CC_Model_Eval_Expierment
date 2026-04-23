<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Support\ProcessExecutor;

final class PhpunitCheck implements CheckInterface
{
    private readonly ProcessExecutor $executor;

    public function __construct(
        private readonly ?string $filter = null,
        private readonly int $runs = 1,
        ?ProcessExecutor $executor = null,
    ) {
        $this->executor = $executor ?? new ProcessExecutor();
    }

    public function run(string $worktreePath): CheckResult
    {
        $command = ['./vendor/bin/phpunit'];
        if ($this->filter !== null) {
            $command[] = '--filter';
            $command[] = $this->filter;
        }

        $cwd = $worktreePath . '/mock-project';
        $outcomes = [];
        for ($i = 0; $i < $this->runs; $i++) {
            $res = $this->executor->exec($cwd, $command);
            $outcomes[] = $res->exitCode;
        }

        $passed = !in_array(true, array_map(static fn(int $c) => $c !== 0, $outcomes), true);

        return new CheckResult(
            type: 'phpunit',
            passed: $passed,
            details: [
                'runs' => $this->runs,
                'per_run_outcomes' => $outcomes,
                'filter' => $this->filter,
            ],
        );
    }
}
