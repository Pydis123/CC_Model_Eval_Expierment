<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

interface ClaudeCli
{
    /**
     * @param list<string> $allowedTools
     */
    public function dispatch(
        string $prompt,
        string $modelId,
        string $cwd,
        array $allowedTools,
    ): ClaudeCliResponse;
}
