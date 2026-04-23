<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

use LlmDispatch\Runner\Evaluator\EvaluationResult;

final class RunOutcome
{
    /**
     * @param list<IterationOutcome> $iterations
     */
    public function __construct(
        public readonly array $iterations,
        public readonly string $finalOutcome,
        public readonly ?string $errorCategory,
    ) {}

    public function iterationsUsed(): int
    {
        return count($this->iterations);
    }

    public function totalTokensIn(): int
    {
        return array_sum(array_map(static fn(IterationOutcome $it) => $it->tokensIn, $this->iterations));
    }

    public function totalTokensOut(): int
    {
        return array_sum(array_map(static fn(IterationOutcome $it) => $it->tokensOut, $this->iterations));
    }

    public function totalWallClockS(): int
    {
        return array_sum(array_map(static fn(IterationOutcome $it) => $it->wallClockS, $this->iterations));
    }

    public function lastEvaluation(): ?EvaluationResult
    {
        for ($i = count($this->iterations) - 1; $i >= 0; $i--) {
            if ($this->iterations[$i]->evaluation !== null) {
                return $this->iterations[$i]->evaluation;
            }
        }
        return null;
    }
}
