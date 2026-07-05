<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\ClaudeCliResponseParser;
use LlmDispatch\Runner\Dispatch\ProcessClaudeCli;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class ProcessClaudeCliTest extends TestCase
{
    public function testBuildsExpectedCommand(): void
    {
        $capturedCommand = null;
        $capturedCwd = null;
        $capturedEnv = null;

        $executor = new class($capturedCommand, $capturedCwd, $capturedEnv) extends ProcessExecutor {
            /** @param null|list<string> $capturedCommand */
            public function __construct(
                private mixed &$capturedCommand,
                private mixed &$capturedCwd,
                private mixed &$capturedEnv,
            ) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->capturedCommand = $command;
                $this->capturedCwd = $cwd;
                $this->capturedEnv = $env;
                return new ProcessResult(0, json_encode([
                    'type' => 'result',
                    'subtype' => 'success',
                    'is_error' => false,
                    'result' => 'ok',
                    'stop_reason' => 'end_turn',
                    'duration_ms' => 10,
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                    'modelUsage' => ['claude-haiku-4-5-20251001' => []],
                    'total_cost_usd' => 0.0,
                ]) . "\n", '', 0);
            }
        };

        $cli = new ProcessClaudeCli($executor, new ClaudeCliResponseParser(), 'claude');

        $cli->dispatch(
            prompt: 'hello',
            modelId: 'claude-haiku-4-5-20251001',
            cwd: '/tmp/worktree',
            allowedTools: ['Bash', 'Edit', 'Read', 'Write', 'Glob', 'Grep'],
        );

        $this->assertSame('/tmp/worktree', $capturedCwd);
        $this->assertSame('claude', $capturedCommand[0]);
        $this->assertContains('-p', $capturedCommand);
        $this->assertContains('hello', $capturedCommand);
        $this->assertContains('--model', $capturedCommand);
        $this->assertContains('claude-haiku-4-5-20251001', $capturedCommand);
        $this->assertContains('--output-format', $capturedCommand);
        $this->assertContains('stream-json', $capturedCommand);
        $this->assertContains('--verbose', $capturedCommand);
        $this->assertContains('--no-session-persistence', $capturedCommand);
        $this->assertContains('--allowedTools', $capturedCommand);
        $this->assertContains('Bash,Edit,Read,Write,Glob,Grep', $capturedCommand);
    }

    public function testParsesResponseFromStdout(): void
    {
        $executor = new class extends ProcessExecutor {
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                return new ProcessResult(0, json_encode([
                    'type' => 'result',
                    'subtype' => 'success',
                    'is_error' => false,
                    'result' => 'the response',
                    'stop_reason' => 'end_turn',
                    'duration_ms' => 123,
                    'usage' => ['input_tokens' => 42, 'output_tokens' => 17],
                    'modelUsage' => ['claude-sonnet-4-6' => []],
                    'total_cost_usd' => 0.05,
                ]) . "\n", '', 0);
            }
        };

        $cli = new ProcessClaudeCli($executor, new ClaudeCliResponseParser(), 'claude');

        $response = $cli->dispatch('p', 'claude-sonnet-4-6', '/tmp/w', ['Bash']);

        $this->assertSame('the response', $response->resultText);
        $this->assertSame(42, $response->inputTokens);
        $this->assertSame(17, $response->outputTokens);
        $this->assertSame(123, $response->durationMs);
        $this->assertEqualsWithDelta(0.05, $response->costUsd, 0.00001);
    }

    public function testCommandIncludesSettingSourcesProject(): void
    {
        $capturedCommand = null;
        $capturedCwd = null;
        $capturedEnv = null;

        $executor = new class($capturedCommand, $capturedCwd, $capturedEnv) extends ProcessExecutor {
            /** @param null|list<string> $capturedCommand */
            public function __construct(
                private mixed &$capturedCommand,
                private mixed &$capturedCwd,
                private mixed &$capturedEnv,
            ) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->capturedCommand = $command;
                $this->capturedCwd = $cwd;
                $this->capturedEnv = $env;
                return new ProcessResult(0, json_encode([
                    'type' => 'result',
                    'subtype' => 'success',
                    'is_error' => false,
                    'result' => 'ok',
                    'stop_reason' => 'end_turn',
                    'duration_ms' => 10,
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                    'modelUsage' => ['claude-haiku-4-5-20251001' => []],
                    'total_cost_usd' => 0.0,
                ]) . "\n", '', 0);
            }
        };

        $cli = new ProcessClaudeCli($executor, new ClaudeCliResponseParser(), 'claude');

        $cli->dispatch(
            prompt: 'hello',
            modelId: 'claude-haiku-4-5-20251001',
            cwd: '/tmp/worktree',
            allowedTools: ['Bash'],
        );

        $this->assertContains('--setting-sources', $capturedCommand);
        $settingSourcesIndex = array_search('--setting-sources', $capturedCommand, true);
        $this->assertIsInt($settingSourcesIndex);
        $this->assertSame('project', $capturedCommand[$settingSourcesIndex + 1]);
    }

    public function testEnvironmentStripsClaudeAndAnthropicVarsAndPinsAutoupdater(): void
    {
        $capturedCommand = null;
        $capturedCwd = null;
        $capturedEnv = null;

        $executor = new class($capturedCommand, $capturedCwd, $capturedEnv) extends ProcessExecutor {
            /** @param null|list<string> $capturedCommand */
            public function __construct(
                private mixed &$capturedCommand,
                private mixed &$capturedCwd,
                private mixed &$capturedEnv,
            ) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->capturedCommand = $command;
                $this->capturedCwd = $cwd;
                $this->capturedEnv = $env;
                return new ProcessResult(0, json_encode([
                    'type' => 'result',
                    'subtype' => 'success',
                    'is_error' => false,
                    'result' => 'ok',
                    'stop_reason' => 'end_turn',
                    'duration_ms' => 10,
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                    'modelUsage' => ['claude-haiku-4-5-20251001' => []],
                    'total_cost_usd' => 0.0,
                ]) . "\n", '', 0);
            }
        };

        $originalPath = (string) getenv('PATH');
        try {
            putenv('ANTHROPIC_MODEL=test-leak');
            putenv('CLAUDE_TEST_VAR=x');
            putenv('PATH=/fake/bin-dir:/usr/bin');

            $cli = new ProcessClaudeCli($executor, new ClaudeCliResponseParser(), '/fake/bin-dir/claude');

            $cli->dispatch(
                prompt: 'hello',
                modelId: 'claude-haiku-4-5-20251001',
                cwd: '/tmp/worktree',
                allowedTools: ['Bash'],
            );

            // Assertions
            $this->assertIsArray($capturedEnv);
            $this->assertArrayNotHasKey('ANTHROPIC_MODEL', $capturedEnv);
            $this->assertArrayNotHasKey('CLAUDE_TEST_VAR', $capturedEnv);
            $this->assertSame('1', $capturedEnv['DISABLE_AUTOUPDATER']);
            $this->assertStringNotContainsString('/fake/bin-dir', $capturedEnv['PATH']);
            $this->assertStringContainsString('/usr/bin', $capturedEnv['PATH']);
        } finally {
            putenv('ANTHROPIC_MODEL');
            putenv('CLAUDE_TEST_VAR');
            putenv('PATH=' . $originalPath);
        }
    }
}
