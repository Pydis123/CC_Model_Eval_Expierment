<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\DiffSizeLimitCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class DiffSizeLimitCheckTest extends TestCase
{
    public function testPassesWhenDiffIsUnderLimit(): void
    {
        $numstat = "5\t2\tfoo.php\n10\t3\tbar.php\n";
        $stub = $this->stubExecutor(new ProcessResult(0, $numstat, ''));

        $check = new DiffSizeLimitCheck(maxLines: 50, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('diff_size_limit', $result->type);
        $this->assertSame(20, $result->details['actual_lines']);
        $this->assertSame(50, $result->details['max_lines']);
    }

    public function testFailsWhenDiffExceedsLimit(): void
    {
        $numstat = "100\t200\tbig.php\n";
        $stub = $this->stubExecutor(new ProcessResult(0, $numstat, ''));

        $check = new DiffSizeLimitCheck(maxLines: 50, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertSame(300, $result->details['actual_lines']);
    }

    public function testSkipsBinaryFilesMarkedWithDashes(): void
    {
        $numstat = "5\t2\tfoo.php\n-\t-\tbinary.png\n";
        $stub = $this->stubExecutor(new ProcessResult(0, $numstat, ''));

        $check = new DiffSizeLimitCheck(maxLines: 50, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertSame(7, $result->details['actual_lines']);
    }

    public function testReturnsFailedWhenGitCommandExitsNonZero(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(128, '', "fatal: scaffold_complete not found\n"));

        $check = new DiffSizeLimitCheck(maxLines: 50, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('scaffold_complete', $result->details['error']);
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
