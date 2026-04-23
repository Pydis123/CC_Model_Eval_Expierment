<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator;

final class CheckResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $type,
        public readonly bool $passed,
        public readonly array $details,
        public readonly float $wallClockS = 0.0,
    ) {}

    public function withWallClock(float $seconds): self
    {
        return new self($this->type, $this->passed, $this->details, $seconds);
    }

    /**
     * @return array{type: string, passed: bool, wall_clock_s: float, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'passed' => $this->passed,
            'wall_clock_s' => $this->wallClockS,
            'details' => $this->details,
        ];
    }
}
