<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\QueryCountCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QueryCountCheckTest extends TestCase
{
    public function testPassesWhenActualAtMostMax(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(0, '{"route":"/foo","query_count":3}', ''));
        $check = new QueryCountCheck(route: '/foo', max: 3, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('query_count', $result->type);
        $this->assertSame(['route' => '/foo', 'max' => 3, 'actual' => 3], $result->details);
    }

    public function testFailsWhenActualAboveMax(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(0, '{"route":"/foo","query_count":3}', ''));
        $check = new QueryCountCheck(route: '/foo', max: 2, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertSame(3, $result->details['actual']);
    }

    public function testThrowsRuntimeExceptionOnNonZeroExit(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(1, '', 'mock-project vendor missing'));
        $check = new QueryCountCheck(route: '/foo', max: 5, executor: $stub);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/mock-project vendor missing/');

        $check->run('/tmp/worktree');
    }

    public function testCommandIncludesProbeQueryCountWorktreeAndRoute(): void
    {
        $capturedCommand = null;
        $stub = new class($capturedCommand) extends ProcessExecutor {
            public function __construct(public mixed &$captured) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->captured = $command;
                return new ProcessResult(0, '{"route":"/foo","query_count":1}', '');
            }
        };

        $check = new QueryCountCheck(
            route: '/foo',
            max: 5,
            authAsAdmin: false,
            executor: $stub,
            cliBinary: '/runner/bin/cli',
        );
        $check->run('/tmp/worktree');

        $this->assertContains('probe', $capturedCommand);
        $this->assertContains('query-count', $capturedCommand);
        $this->assertContains('--worktree=/tmp/worktree', $capturedCommand);
        $this->assertContains('--route=/foo', $capturedCommand);
        $this->assertNotContains('--auth-as-admin', $capturedCommand);
    }

    public function testCommandIncludesAuthAsAdminFlagWhenEnabled(): void
    {
        $capturedCommand = null;
        $stub = new class($capturedCommand) extends ProcessExecutor {
            public function __construct(public mixed &$captured) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->captured = $command;
                return new ProcessResult(0, '{"route":"/foo","query_count":1}', '');
            }
        };

        $check = new QueryCountCheck(
            route: '/foo',
            max: 5,
            authAsAdmin: true,
            executor: $stub,
            cliBinary: '/runner/bin/cli',
        );
        $check->run('/tmp/worktree');

        $this->assertContains('--auth-as-admin', $capturedCommand);
    }

    private function stubExecutor(ProcessResult $result): ProcessExecutor
    {
        return new class($result) extends ProcessExecutor {
            public function __construct(private ProcessResult $stub) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                return $this->stub;
            }
        };
    }
}
