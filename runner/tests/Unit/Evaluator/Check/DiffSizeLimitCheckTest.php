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
        $numstat = "5\t2\tmock-project/foo.php\n10\t3\tmock-project/bar.php\n";
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
        $numstat = "100\t200\tmock-project/big.php\n";
        $stub = $this->stubExecutor(new ProcessResult(0, $numstat, ''));

        $check = new DiffSizeLimitCheck(maxLines: 50, executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertSame(300, $result->details['actual_lines']);
    }

    public function testSkipsBinaryFilesMarkedWithDashes(): void
    {
        $numstat = "5\t2\tmock-project/foo.php\n-\t-\tmock-project/binary.png\n";
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

    public function testIgnoresPathsOutsideMockProject(): void
    {
        // Mix of mock-project files and root infra files
        $numstat = "10\t5\tmock-project/src/foo.php\n0\t500\trunner/bin/cli\n0\t80\tCLAUDE.md\n5\t2\tmock-project/test/bar.php\n";
        $stub = $this->stubExecutor(new ProcessResult(0, $numstat, ''));

        $check = new DiffSizeLimitCheck(maxLines: 40, executor: $stub);

        $result = $check->run('/tmp/worktree');

        // Only mock-project files count: (10+5) + (5+2) = 22 lines
        $this->assertTrue($result->passed);
        $this->assertSame(22, $result->details['actual_lines']);
        $this->assertSame(2, $result->details['excluded_non_mock_project_files']);

        // per_file should only contain mock-project paths
        $perFile = $result->details['per_file'];
        $this->assertCount(2, $perFile);
        $this->assertSame('mock-project/src/foo.php', $perFile[0]['file']);
        $this->assertSame('mock-project/test/bar.php', $perFile[1]['file']);
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
