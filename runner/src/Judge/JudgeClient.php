<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Judge;

use LlmDispatch\Runner\Dispatch\ClaudeCli;
use RuntimeException;

final class JudgeClient
{
    public function __construct(
        private readonly ClaudeCli $cli,
        private readonly string $judgeModelId,
        private readonly string $cwd,
    ) {}

    /**
     * @return array<mixed>
     */
    public function judgeJson(string $prompt): array
    {
        $response = $this->cli->dispatch($prompt, $this->judgeModelId, $this->cwd, []);
        $decoded = $this->extract($response->resultText);

        if ($decoded !== null) {
            return $decoded;
        }

        // Retry once with the error suffix
        $retryPrompt = $prompt . "\n\nYour previous reply was not valid JSON. Reply with ONLY a JSON object.";
        $retryResponse = $this->cli->dispatch($retryPrompt, $this->judgeModelId, $this->cwd, []);
        $decodedRetry = $this->extract($retryResponse->resultText);

        if ($decodedRetry !== null) {
            return $decodedRetry;
        }

        throw new RuntimeException('Judge returned undecodable output');
    }

    /**
     * @return array<mixed>|null
     */
    private function extract(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }
}
