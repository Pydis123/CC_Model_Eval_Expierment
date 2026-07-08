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
        // The CLI reports 'allowed_warning' when usage approaches a limit;
        // requests still succeed, so only a genuinely rejecting status blocks.
        return $this->status !== 'allowed' && $this->status !== 'allowed_warning';
    }
}
