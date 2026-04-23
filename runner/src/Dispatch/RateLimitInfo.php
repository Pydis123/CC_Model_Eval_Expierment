<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

final class RateLimitInfo
{
    public function __construct(
        public readonly string $status,
        public readonly ?int $resetsAt,
    ) {}

    public function isBlocked(): bool
    {
        return $this->status !== 'allowed';
    }
}
