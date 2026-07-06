<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

use LlmDispatch\Runner\Evaluator\EvaluationResult;

final class IterationOutcome
{
    public function __construct(
        public readonly int $index,
        public readonly string $promptUsed,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly int $wallClockS,
        public readonly string $modelIdReported,
        public readonly float $costUsd,
        public readonly ?EvaluationResult $evaluation,
        public readonly ?string $errorCategory,
        public readonly string $resultText = '',
        public readonly string $transcript = '',
    ) {}

    public function totalTokens(): int
    {
        return $this->tokensIn + $this->tokensOut;
    }

    public function evaluatorOutcome(): string
    {
        if ($this->errorCategory !== null) {
            return 'error';
        }
        return $this->evaluation?->outcome ?? 'error';
    }
}
