<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

use JsonException;

final class ClaudeCliResponseParser
{
    public function parse(string $stdout, string $stderr, int $exitCode): ClaudeCliResponse
    {
        $lines = preg_split('/\r?\n/', $stdout) ?: [];

        /** @var array<string, mixed>|null $resultEvent */
        $resultEvent = null;
        /** @var array<string, mixed>|null $lastRateLimitEvent */
        $lastRateLimitEvent = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $event */
                $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new MalformedClaudeOutputException(
                    "Invalid JSON line in stream: {$line}",
                    previous: $e,
                );
            }

            $type = (string) ($event['type'] ?? '');
            if ($type === 'result') {
                $resultEvent = $event;
            } elseif ($type === 'rate_limit_event') {
                $lastRateLimitEvent = $event;
            }
        }

        if ($resultEvent === null) {
            throw new MalformedClaudeOutputException('No result event in claude -p stream output');
        }

        $rateLimit = $this->parseRateLimit($lastRateLimitEvent);

        /** @var array<string, mixed> $usage */
        $usage = (array) ($resultEvent['usage'] ?? []);

        /** @var array<string, mixed> $modelUsage */
        $modelUsage = (array) ($resultEvent['modelUsage'] ?? []);
        $modelIdReported = (string) (array_key_first($modelUsage) ?? '');

        return new ClaudeCliResponse(
            isError: (bool) ($resultEvent['is_error'] ?? false),
            resultText: (string) ($resultEvent['result'] ?? ''),
            modelIdReported: $modelIdReported,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            durationMs: (int) ($resultEvent['duration_ms'] ?? 0),
            stopReason: (string) ($resultEvent['stop_reason'] ?? ''),
            costUsd: (float) ($resultEvent['total_cost_usd'] ?? 0.0),
            rateLimit: $rateLimit,
            rawStdout: $stdout,
            rawStderr: $stderr,
            exitCode: $exitCode,
        );
    }

    /**
     * @param array<string, mixed>|null $event
     */
    private function parseRateLimit(?array $event): RateLimitInfo
    {
        if ($event === null) {
            return new RateLimitInfo(status: 'allowed', resetsAt: null);
        }

        /** @var array<string, mixed> $info */
        $info = (array) ($event['rate_limit_info'] ?? []);

        return new RateLimitInfo(
            status: (string) ($info['status'] ?? 'allowed'),
            resetsAt: isset($info['resetsAt']) ? (int) $info['resetsAt'] : null,
        );
    }
}
