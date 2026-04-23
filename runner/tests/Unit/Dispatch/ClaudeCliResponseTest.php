<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\ClaudeCliResponse;
use LlmDispatch\Runner\Dispatch\RateLimitInfo;
use PHPUnit\Framework\TestCase;

final class ClaudeCliResponseTest extends TestCase
{
    public function testStoresAllFields(): void
    {
        $rateLimit = new RateLimitInfo(status: 'allowed', resetsAt: 1_800_000_000);

        $response = new ClaudeCliResponse(
            isError: false,
            resultText: 'done',
            modelIdReported: 'claude-haiku-4-5-20251001',
            inputTokens: 100,
            outputTokens: 50,
            durationMs: 12_345,
            stopReason: 'end_turn',
            costUsd: 0.0123,
            rateLimit: $rateLimit,
            rawStdout: '{"type":"result"...}',
            rawStderr: '',
            exitCode: 0,
        );

        $this->assertFalse($response->isError);
        $this->assertSame('done', $response->resultText);
        $this->assertSame('claude-haiku-4-5-20251001', $response->modelIdReported);
        $this->assertSame(100, $response->inputTokens);
        $this->assertSame(50, $response->outputTokens);
        $this->assertSame(150, $response->totalTokens());
        $this->assertSame(12_345, $response->durationMs);
        $this->assertEqualsWithDelta(12.345, $response->durationSeconds(), 0.0001);
        $this->assertSame('allowed', $response->rateLimit->status);
    }

    public function testRateLimitInfoIsBlockedHelper(): void
    {
        $blocked = new RateLimitInfo(status: 'blocked', resetsAt: 1_800_000_000);
        $throttled = new RateLimitInfo(status: 'throttled', resetsAt: 1_800_000_000);
        $allowed = new RateLimitInfo(status: 'allowed', resetsAt: null);

        $this->assertTrue($blocked->isBlocked());
        $this->assertTrue($throttled->isBlocked());
        $this->assertFalse($allowed->isBlocked());
    }
}
