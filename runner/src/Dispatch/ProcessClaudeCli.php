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
            '--setting-sources', 'project',
        ];

        $env = $this->buildEnvironment();
        $result = $this->executor->exec($cwd, $command, $env);

        return $this->parser->parse($result->stdout, $result->stderr, $result->exitCode);
    }

    /**
     * Build a sanitized environment for the dispatched claude CLI process.
     * Strips operator-level CLAUDE/ANTHROPIC overrides, disables autoupdater,
     * and removes the claude binary's directory from PATH.
     *
     * @return array<string, string>
     */
    private function buildEnvironment(): array
    {
        $env = [];
        foreach (getenv() as $key => $value) {
            if (preg_match('/^(CLAUDE|ANTHROPIC)/i', (string) $key) === 1) {
                continue; // no operator-level model/API overrides may leak into runs
            }
            $env[$key] = $value;
        }
        // The dispatched CLI must not silently self-update mid-experiment.
        $env['DISABLE_AUTOUPDATER'] = '1';
        // Remove the claude binary's own directory from the child PATH so the
        // model cannot spawn nested claude sessions via its Bash tool.
        $claudeDir = dirname($this->claudeBinary);
        $parts = array_filter(
            explode(':', (string) ($env['PATH'] ?? '')),
            static fn (string $p): bool => $p !== $claudeDir,
        );
        $env['PATH'] = implode(':', $parts);
        return $env;
    }
}
