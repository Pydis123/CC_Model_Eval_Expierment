<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator;

final class EvaluationResult
{
    public readonly string $outcome;

    /**
     * @param list<CheckResult> $checks
     */
    public function __construct(
        public readonly array $checks,
        public readonly float $wallClockS,
    ) {
        $this->outcome = $this->computeOutcome($checks);
    }

    /**
     * @param list<CheckResult> $checks
     */
    private function computeOutcome(array $checks): string
    {
        foreach ($checks as $check) {
            if (!$check->passed) {
                return 'failed';
            }
        }
        return 'passed';
    }

    /**
     * @return ?array<string, mixed>
     */
    public function metrics(): ?array
    {
        $merged = null;
        foreach ($this->checks as $check) {
            $metrics = $check->details['metrics'] ?? null;
            if (!is_array($metrics)) {
                continue;
            }
            $merged = array_merge($merged ?? [], $metrics);
        }
        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'wall_clock_s' => $this->wallClockS,
            'checks' => array_map(static fn(CheckResult $c) => $c->toArray(), $this->checks),
        ];
    }
}
