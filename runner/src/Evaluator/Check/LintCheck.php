<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Support\ProcessExecutor;

final class LintCheck implements CheckInterface
{
    private readonly ProcessExecutor $executor;

    public function __construct(?ProcessExecutor $executor = null)
    {
        $this->executor = $executor ?? new ProcessExecutor();
    }

    public function run(string $worktreePath): CheckResult
    {
        $cwd = $worktreePath . '/mock-project';
        $res = $this->executor->exec($cwd, ['./vendor/bin/phpstan', 'analyse', '--no-progress']);

        return new CheckResult(
            type: 'lint',
            passed: $res->exitCode === 0,
            details: [
                'output' => trim($res->stdout . "\n" . $res->stderr),
                'exit_code' => $res->exitCode,
            ],
        );
    }
}
