<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Support\ProcessExecutor;

final class GrepNotPresentCheck implements CheckInterface
{
    private readonly ProcessExecutor $executor;

    /**
     * @param list<string> $paths
     */
    public function __construct(
        private readonly string $pattern,
        private readonly array $paths,
        ?ProcessExecutor $executor = null,
    ) {
        $this->executor = $executor ?? new ProcessExecutor();
    }

    public function run(string $worktreePath): CheckResult
    {
        $command = array_merge(['grep', '-rn', $this->pattern], $this->paths);
        $res = $this->executor->exec($worktreePath, $command);

        $matches = [];
        if ($res->exitCode === 0 && $res->stdout !== '') {
            $matches = array_values(array_filter(explode("\n", trim($res->stdout))));
        }

        return new CheckResult(
            type: 'grep_not_present',
            passed: $matches === [],
            details: [
                'pattern' => $this->pattern,
                'paths' => $this->paths,
                'matches' => $matches,
            ],
        );
    }
}
