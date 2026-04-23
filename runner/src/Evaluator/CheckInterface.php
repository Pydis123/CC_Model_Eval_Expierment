<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator;

interface CheckInterface
{
    public function run(string $worktreePath): CheckResult;
}
