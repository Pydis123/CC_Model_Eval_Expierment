<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\ClaudeCliResponseParser;
use LlmDispatch\Runner\Dispatch\MalformedClaudeOutputException;
use PHPUnit\Framework\TestCase;

final class ClaudeCliResponseParserTest extends TestCase
{
    private function successStream(): string
    {
        return implode("\n", [
            json_encode(['type' => 'system', 'subtype' => 'init', 'model' => 'claude-haiku-4-5-20251001']),
            json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'ok']]]]),
            json_encode([
                'type' => 'rate_limit_event',
                'rate_limit_info' => ['status' => 'allowed', 'resetsAt' => 1_800_000_000],
            ]),
            json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'HELLO',
                'stop_reason' => 'end_turn',
                'duration_ms' => 1947,
                'usage' => ['input_tokens' => 6, 'output_tokens' => 8],
                'modelUsage' => ['claude-haiku-4-5-20251001' => ['inputTokens' => 6, 'outputTokens' => 8]],
                'total_cost_usd' => 0.0123,
            ]),
        ]) . "\n";
    }

    public function testParsesSuccessStream(): void
    {
        $response = (new ClaudeCliResponseParser())->parse(
            stdout: $this->successStream(),
            stderr: '',
            exitCode: 0,
        );

        $this->assertFalse($response->isError);
        $this->assertSame('HELLO', $response->resultText);
        $this->assertSame('claude-haiku-4-5-20251001', $response->modelIdReported);
        $this->assertSame(6, $response->inputTokens);
        $this->assertSame(8, $response->outputTokens);
        $this->assertSame(1947, $response->durationMs);
        $this->assertSame('end_turn', $response->stopReason);
        $this->assertEqualsWithDelta(0.0123, $response->costUsd, 0.00001);
        $this->assertSame('allowed', $response->rateLimit->status);
        $this->assertSame(1_800_000_000, $response->rateLimit->resetsAt);
        $this->assertSame(0, $response->exitCode);
    }

    public function testParsesRateLimitedStream(): void
    {
        $stream = implode("\n", [
            json_encode(['type' => 'system', 'subtype' => 'init']),
            json_encode([
                'type' => 'rate_limit_event',
                'rate_limit_info' => ['status' => 'blocked', 'resetsAt' => 1_800_000_000],
            ]),
            json_encode([
                'type' => 'result',
                'subtype' => 'error',
                'is_error' => true,
                'result' => 'rate limit reached',
                'stop_reason' => 'error',
                'duration_ms' => 100,
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
                'modelUsage' => new \stdClass(),
                'total_cost_usd' => 0.0,
            ]),
        ]) . "\n";

        $response = (new ClaudeCliResponseParser())->parse($stream, '', 1);

        $this->assertTrue($response->isError);
        $this->assertTrue($response->rateLimit->isBlocked());
        $this->assertSame(1_800_000_000, $response->rateLimit->resetsAt);
    }

    public function testTakesLastRateLimitEventIfMultiple(): void
    {
        $stream = implode("\n", [
            json_encode(['type' => 'system', 'subtype' => 'init']),
            json_encode([
                'type' => 'rate_limit_event',
                'rate_limit_info' => ['status' => 'throttled', 'resetsAt' => 1_700_000_000],
            ]),
            json_encode([
                'type' => 'rate_limit_event',
                'rate_limit_info' => ['status' => 'allowed', 'resetsAt' => 1_800_000_000],
            ]),
            json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'ok',
                'stop_reason' => 'end_turn',
                'duration_ms' => 100,
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                'modelUsage' => ['claude-sonnet-4-6' => []],
                'total_cost_usd' => 0.001,
            ]),
        ]) . "\n";

        $response = (new ClaudeCliResponseParser())->parse($stream, '', 0);

        $this->assertSame('allowed', $response->rateLimit->status);
        $this->assertSame(1_800_000_000, $response->rateLimit->resetsAt);
    }

    public function testThrowsOnMissingResultEvent(): void
    {
        $stream = json_encode(['type' => 'system', 'subtype' => 'init']) . "\n";

        $this->expectException(MalformedClaudeOutputException::class);
        $this->expectExceptionMessage('No result event');

        (new ClaudeCliResponseParser())->parse($stream, '', 0);
    }

    public function testThrowsOnInvalidJsonLine(): void
    {
        $stream = "not valid json\n" . json_encode([
            'type' => 'result',
            'subtype' => 'success',
            'is_error' => false,
            'result' => 'x',
            'stop_reason' => 'end_turn',
            'duration_ms' => 1,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            'modelUsage' => ['m' => []],
            'total_cost_usd' => 0.0,
        ]) . "\n";

        $this->expectException(MalformedClaudeOutputException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');

        (new ClaudeCliResponseParser())->parse($stream, '', 0);
    }

    public function testDefaultsMissingRateLimitToAllowed(): void
    {
        $stream = implode("\n", [
            json_encode(['type' => 'system', 'subtype' => 'init']),
            json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'ok',
                'stop_reason' => 'end_turn',
                'duration_ms' => 10,
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                'modelUsage' => ['claude-opus-4-7' => []],
                'total_cost_usd' => 0.0,
            ]),
        ]) . "\n";

        $response = (new ClaudeCliResponseParser())->parse($stream, '', 0);

        $this->assertSame('allowed', $response->rateLimit->status);
        $this->assertNull($response->rateLimit->resetsAt);
    }

    public function testModelUsageKeyExtraction(): void
    {
        $stream = implode("\n", [
            json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'ok',
                'stop_reason' => 'end_turn',
                'duration_ms' => 10,
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                'modelUsage' => ['claude-opus-4-7[1m]' => []],
                'total_cost_usd' => 0.0,
            ]),
        ]) . "\n";

        $response = (new ClaudeCliResponseParser())->parse($stream, '', 0);

        $this->assertSame('claude-opus-4-7[1m]', $response->modelIdReported);
    }

    public function testIgnoresBlankLines(): void
    {
        $stream = "\n\n" . json_encode([
            'type' => 'result',
            'subtype' => 'success',
            'is_error' => false,
            'result' => 'ok',
            'stop_reason' => 'end_turn',
            'duration_ms' => 10,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            'modelUsage' => ['claude-haiku-4-5-20251001' => []],
            'total_cost_usd' => 0.0,
        ]) . "\n\n";

        $response = (new ClaudeCliResponseParser())->parse($stream, '', 0);

        $this->assertFalse($response->isError);
    }
}
