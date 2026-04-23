<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\LintCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class LintCheckTest extends TestCase
{
    public function testPassesWhenPhpstanExitsZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(0, "[OK] No errors\n", ''));

        $check = new LintCheck(executor: $stub);
        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('lint', $result->type);
    }

    public function testFailsWhenPhpstanExitsNonZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(
            1,
            "src/Foo.php:12: Parameter \$x of method Foo::bar() has invalid type\n",
            ''
        ));

        $check = new LintCheck(executor: $stub);
        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('invalid type', $result->details['output']);
    }

    public function testCommandRunsPhpstanAnalyseInMockProject(): void
    {
        $captured = null;
        $capturedCwd = null;
        $stub = new class($captured, $capturedCwd) extends ProcessExecutor {
            public function __construct(public mixed &$captured, public mixed &$capturedCwd) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->captured = $command;
                $this->capturedCwd = $cwd;
                return new ProcessResult(0, '', '');
            }
        };

        $check = new LintCheck(executor: $stub);
        $check->run('/tmp/worktree');

        $this->assertContains('./vendor/bin/phpstan', $captured);
        $this->assertContains('analyse', $captured);
        $this->assertSame('/tmp/worktree/mock-project', $capturedCwd);
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
