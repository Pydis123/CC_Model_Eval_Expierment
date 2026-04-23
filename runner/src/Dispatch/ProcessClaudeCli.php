<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

use LlmDispatch\Runner\Support\ProcessExecutor;

final class ProcessClaudeCli implements ClaudeCli
{
    public function __construct(
        private readonly ProcessExecutor $executor,
        private readonly ClaudeCliResponseParser $parser,
        private readonly string $claudeBinary,
    ) {}

    public function dispatch(
        string $prompt,
        string $modelId,
        string $cwd,
        array $allowedTools,
    ): ClaudeCliResponse {
        $command = [
            $this->claudeBinary,
            '-p', $prompt,
            '--model', $modelId,
            '--output-format', 'stream-json',
            '--verbose',
            '--no-session-persistence',
            '--allowedTools', implode(',', $allowedTools),
        ];

        $result = $this->executor->exec($cwd, $command);

        return $this->parser->parse($result->stdout, $result->stderr, $result->exitCode);
    }
}
