<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\RateLimitInfo;
use LlmDispatch\Runner\Dispatch\RateLimitWaiter;
use PHPUnit\Framework\TestCase;

final class RateLimitWaiterTest extends TestCase
{
    public function testBlockedFutureResetsAtReturnsDeltaPlusBuffer(): void
    {
        $waiter = new RateLimitWaiter(bufferSeconds: 30);

        $seconds = $waiter->computeSleepSeconds(
            rateLimit: new RateLimitInfo(status: 'blocked', resetsAt: 2_000_000_100),
            now: 2_000_000_000,
        );

        $this->assertSame(130, $seconds);
    }

    public function testBlockedPastResetsAtReturnsBufferOnly(): void
    {
        $waiter = new RateLimitWaiter(bufferSeconds: 30);

        $seconds = $waiter->computeSleepSeconds(
            rateLimit: new RateLimitInfo(status: 'blocked', resetsAt: 1_000_000_000),
            now: 2_000_000_000,
        );

        $this->assertSame(30, $seconds);
    }

    public function testAllowedReturnsZero(): void
    {
        $waiter = new RateLimitWaiter(bufferSeconds: 30);

        $seconds = $waiter->computeSleepSeconds(
            rateLimit: new RateLimitInfo(status: 'allowed', resetsAt: 2_000_000_100),
            now: 2_000_000_000,
        );

        $this->assertSame(0, $seconds);
    }

    public function testAllowedWarningIsNotBlockedAndReturnsZero(): void
    {
        $waiter = new RateLimitWaiter(bufferSeconds: 30);

        $info = new RateLimitInfo(status: 'allowed_warning', resetsAt: 2_000_035_000);

        $this->assertFalse($info->isBlocked());
        $this->assertSame(0, $waiter->computeSleepSeconds($info, now: 2_000_000_000));
    }

    public function testBlockedNullResetsAtFallsBackToBuffer(): void
    {
        $waiter = new RateLimitWaiter(bufferSeconds: 30);

        $seconds = $waiter->computeSleepSeconds(
            rateLimit: new RateLimitInfo(status: 'blocked', resetsAt: null),
            now: 2_000_000_000,
        );

        $this->assertSame(30, $seconds);
    }
}
