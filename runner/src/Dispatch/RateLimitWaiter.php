<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

final class RateLimitWaiter
{
    public function __construct(private readonly int $bufferSeconds = 30) {}

    public function computeSleepSeconds(RateLimitInfo $rateLimit, int $now): int
    {
        if (!$rateLimit->isBlocked()) {
            return 0;
        }

        if ($rateLimit->resetsAt === null) {
            return $this->bufferSeconds;
        }

        $delta = $rateLimit->resetsAt - $now;
        if ($delta < 0) {
            return $this->bufferSeconds;
        }

        return $delta + $this->bufferSeconds;
    }
}
