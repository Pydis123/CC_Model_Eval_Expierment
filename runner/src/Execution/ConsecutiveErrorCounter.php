<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

final class ConsecutiveErrorCounter
{
    private int $count = 0;

    public function __construct(private readonly int $threshold) {}

    public function recordUnexpectedError(): void
    {
        $this->count++;
    }

    public function recordRegisteredRun(): void
    {
        $this->count = 0;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function shouldAbort(): bool
    {
        return $this->count >= $this->threshold;
    }
}
