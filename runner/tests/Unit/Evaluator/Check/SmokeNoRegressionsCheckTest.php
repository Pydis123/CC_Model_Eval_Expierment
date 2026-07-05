<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\SmokeNoRegressionsCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class SmokeNoRegressionsCheckTest extends TestCase
{
    public function testPassesWhenSmokeSuiteExitsZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(
            0,
            "OK (12 tests, 28 assertions)\n",
            ''
        ));

        $check = new SmokeNoRegressionsCheck(executor: $stub);
        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('smoke_no_regressions', $result->type);
    }

    public function testFailsWhenSmokeSuiteExitsNonZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(
            1,
            "FAILURES! Tests: 12, Assertions: 28, Failures: 1.\n",
            ''
        ));

        $check = new SmokeNoRegressionsCheck(executor: $stub);
        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('FAILURES', $result->details['output']);
    }

    public function testCommandRunsPhpunitSmokeSuiteInMockProject(): void
    {
        $capturedCmd = null;
        $capturedCwd = null;
        $stub = new class($capturedCmd, $capturedCwd) extends ProcessExecutor {
            public function __construct(public mixed &$captured, public mixed &$cwd) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                $this->captured = $command;
                $this->cwd = $cwd;
                return new ProcessResult(0, '', '');
            }
        };

        $check = new SmokeNoRegressionsCheck(executor: $stub);
        $check->run('/tmp/worktree');

        $this->assertContains('./vendor/bin/phpunit', $capturedCmd);
        $this->assertContains('--testsuite', $capturedCmd);
        $this->assertContains('Smoke', $capturedCmd);
        $this->assertSame('/tmp/worktree/mock-project', $capturedCwd);
    }

    private function stubExecutor(ProcessResult $result): ProcessExecutor
    {
        return new class($result) extends ProcessExecutor {
            public function __construct(private ProcessResult $stub) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                return $this->stub;
            }
        };
    }
}
