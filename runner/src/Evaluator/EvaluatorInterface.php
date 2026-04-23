<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator;

interface EvaluatorInterface
{
    /**
     * @param array{success_criteria: list<array<string, mixed>>} $taskDef
     */
    public function evaluate(array $taskDef, string $worktreePath): EvaluationResult;
}
