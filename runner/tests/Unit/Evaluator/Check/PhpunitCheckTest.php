<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\PhpunitCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class PhpunitCheckTest extends TestCase
{
    public function testPassesWhenPhpunitExitsZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(0, "OK (74 tests, 143 assertions)\n", ''));
        $check = new PhpunitCheck(executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('phpunit', $result->type);
        $this->assertSame(1, $result->details['runs']);
        $this->assertSame([0], $result->details['per_run_outcomes']);
    }

    public function testFailsWhenPhpunitExitsNonZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(1, "FAILURES\n", ''));
        $check = new PhpunitCheck(executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
    }

    public function testPassesFilterToPhpunitWhenProvided(): void
    {
        $capturedCommand = null;
        $stub = new class($capturedCommand) extends ProcessExecutor {
            public function __construct(public mixed &$captured) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->captured = $command;
                return new ProcessResult(0, "OK\n", '');
            }
        };
        $check = new PhpunitCheck(filter: 'MyTest::testFoo', executor: $stub);

        $check->run('/tmp/worktree');

        $this->assertContains('--filter', $capturedCommand);
        $this->assertContains('MyTest::testFoo', $capturedCommand);
    }

    public function testRunsMultipleTimesWhenRunsIsGreaterThanOne(): void
    {
        $calls = 0;
        $stub = new class($calls) extends ProcessExecutor {
            public function __construct(public int &$calls) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->calls++;
                return new ProcessResult(0, "OK\n", '');
            }
        };

        $check = new PhpunitCheck(runs: 5, executor: $stub);
        $result = $check->run('/tmp/worktree');

        $this->assertSame(5, $calls);
        $this->assertSame(5, $result->details['runs']);
        $this->assertTrue($result->passed);
    }

    public function testFailsIfAnyOfMultipleRunsFails(): void
    {
        $calls = 0;
        $stub = new class($calls) extends ProcessExecutor {
            public function __construct(public int &$calls) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->calls++;
                return new ProcessResult($this->calls === 3 ? 1 : 0, '', '');
            }
        };

        $check = new PhpunitCheck(runs: 5, executor: $stub);
        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertSame([0, 0, 1, 0, 0], $result->details['per_run_outcomes']);
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
