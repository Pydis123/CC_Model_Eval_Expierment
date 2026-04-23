<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Probe\QueryCountProbe;

final class QueryCountCheck implements CheckInterface
{
    private readonly QueryCountProbe $probe;

    public function __construct(
        private readonly string $route,
        private readonly int $max,
        private readonly bool $authAsAdmin = true,
        ?QueryCountProbe $probe = null,
    ) {
        $this->probe = $probe ?? new QueryCountProbe();
    }

    public function run(string $worktreePath): CheckResult
    {
        $actual = $this->probe->count($worktreePath, $this->route, $this->authAsAdmin);

        return new CheckResult(
            type: 'query_count',
            passed: $actual <= $this->max,
            details: [
                'route' => $this->route,
                'max' => $this->max,
                'actual' => $actual,
            ],
        );
    }
}
