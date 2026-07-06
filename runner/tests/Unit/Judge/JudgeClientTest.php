<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Judge;

use LlmDispatch\Runner\Dispatch\ClaudeCli;
use LlmDispatch\Runner\Dispatch\ClaudeCliResponse;
use LlmDispatch\Runner\Dispatch\RateLimitInfo;
use LlmDispatch\Runner\Judge\JudgeClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JudgeClientTest extends TestCase
{
    public function testDecodesJsonWrappedInProse(): void
    {
        $cli = $this->fakeCli(['Here is my verdict: {"verdict": "hallucination"} — done.']);
        $client = new JudgeClient($cli, 'claude-opus-4-8', '/tmp');
        $this->assertSame(['verdict' => 'hallucination'], $client->judgeJson('p'));
    }

    public function testRetriesOnceThenSucceeds(): void
    {
        $cli = $this->fakeCli(['not json at all', '{"scores": {"a": 2}}']);
        $client = new JudgeClient($cli, 'claude-opus-4-8', '/tmp');
        $this->assertSame(['scores' => ['a' => 2]], $client->judgeJson('p'));
        $this->assertSame(2, $cli->calls);
        $this->assertStringContainsString('not valid JSON', $cli->prompts[1]);
    }

    public function testThrowsAfterFailedRetry(): void
    {
        $cli = $this->fakeCli(['garbage', 'more garbage']);
        $client = new JudgeClient($cli, 'claude-opus-4-8', '/tmp');
        $this->expectException(RuntimeException::class);
        $client->judgeJson('p');
    }

    /** @param list<string> $texts */
    private function fakeCli(array $texts): ClaudeCli
    {
        return new class($texts) implements ClaudeCli {
            public int $calls = 0;
            /** @var list<string> */
            public array $prompts = [];

            /** @param list<string> $texts */
            public function __construct(private array $texts) {}

            public function dispatch(string $prompt, string $modelId, string $cwd, array $allowedTools): ClaudeCliResponse
            {
                $this->prompts[] = $prompt;
                $text = $this->texts[$this->calls] ?? '';
                $this->calls++;
                return new ClaudeCliResponse(
                    isError: false, resultText: $text, modelIdReported: $modelId,
                    inputTokens: 1, outputTokens: 1, durationMs: 1, stopReason: 'end_turn',
                    costUsd: 0.0, rateLimit: new RateLimitInfo('ok', null),
                    rawStdout: '', rawStderr: '', exitCode: 0,
                );
            }
        };
    }
}
