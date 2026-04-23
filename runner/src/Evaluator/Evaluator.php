<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator;

use RuntimeException;

final class Evaluator
{
    /**
     * @param array<string, callable(array<string, mixed>): CheckInterface> $checkFactories
     */
    public function __construct(private readonly array $checkFactories) {}

    /**
     * @param array{success_criteria: list<array<string, mixed>>} $taskDef
     */
    public function evaluate(array $taskDef, string $worktreePath): EvaluationResult
    {
        $t0 = microtime(true);
        $results = [];

        foreach ($taskDef['success_criteria'] as $criterion) {
            $type = (string) ($criterion['type'] ?? '');
            if (!isset($this->checkFactories[$type])) {
                throw new RuntimeException("Unknown check type: {$type}");
            }

            $check = ($this->checkFactories[$type])($criterion);
            $checkStart = microtime(true);
            $result = $check->run($worktreePath);
            $wallClock = microtime(true) - $checkStart;
            $results[] = $result->withWallClock($wallClock);
        }

        return new EvaluationResult($results, microtime(true) - $t0);
    }
}
