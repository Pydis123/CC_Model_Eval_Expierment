<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

final class ClaudeCliResponse
{
    public function __construct(
        public readonly bool $isError,
        public readonly string $resultText,
        public readonly string $modelIdReported,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $durationMs,
        public readonly string $stopReason,
        public readonly float $costUsd,
        public readonly RateLimitInfo $rateLimit,
        public readonly string $rawStdout,
        public readonly string $rawStderr,
        public readonly int $exitCode,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public function durationSeconds(): float
    {
        return $this->durationMs / 1000.0;
    }
}
