<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Integration\Dispatch;

use LlmDispatch\Runner\Dispatch\ClaudeCliResponseParser;
use LlmDispatch\Runner\Dispatch\ProcessClaudeCli;
use LlmDispatch\Runner\Support\ProcessExecutor;
use PHPUnit\Framework\TestCase;

final class RealClaudeSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('SKIP_CLAUDE_SMOKE') === '1') {
            $this->markTestSkipped('SKIP_CLAUDE_SMOKE=1');
        }

        $which = trim((string) shell_exec('which claude 2>/dev/null'));
        if ($which === '') {
            $this->markTestSkipped('claude binary not on PATH');
        }
    }

    public function testRealClaudeDispatchReturnsParsedResponse(): void
    {
        $cli = new ProcessClaudeCli(
            new ProcessExecutor(),
            new ClaudeCliResponseParser(),
            'claude',
        );

        $response = $cli->dispatch(
            prompt: 'Respond with exactly this one word: OK',
            modelId: 'haiku',
            cwd: sys_get_temp_dir(),
            allowedTools: [], // empty = no tools
        );

        $this->assertFalse($response->isError, "is_error was true. stderr: {$response->rawStderr}");
        $this->assertStringContainsString('OK', $response->resultText);
        $this->assertGreaterThan(0, $response->inputTokens);
        $this->assertGreaterThan(0, $response->outputTokens);
        $this->assertNotSame('', $response->modelIdReported);
        $this->assertGreaterThan(0, $response->durationMs);
    }
}
