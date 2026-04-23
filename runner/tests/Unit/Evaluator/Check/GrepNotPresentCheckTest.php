<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\GrepNotPresentCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class GrepNotPresentCheckTest extends TestCase
{
    public function testPassesWhenGrepFindsNothing(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(1, '', ''));
        $check = new GrepNotPresentCheck('TODO', ['src/'], executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('grep_not_present', $result->type);
        $this->assertSame([], $result->details['matches']);
    }

    public function testFailsWhenGrepFindsMatches(): void
    {
        $stub = $this->stubExecutor(new ProcessResult(
            0,
            "src/foo.php:12:// TODO: fix\nsrc/bar.php:4:// TODO\n",
            ''
        ));
        $check = new GrepNotPresentCheck('TODO', ['src/'], executor: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertCount(2, $result->details['matches']);
        $this->assertStringContainsString('src/foo.php', $result->details['matches'][0]);
    }

    public function testCommandIncludesPatternAndPaths(): void
    {
        $captured = null;
        $stub = new class($captured) extends ProcessExecutor {
            public function __construct(public mixed &$captured) {}
            public function exec(string $cwd, array $command): ProcessResult
            {
                $this->captured = $command;
                return new ProcessResult(1, '', '');
            }
        };
        $check = new GrepNotPresentCheck('@phpstan-ignore', ['src/', 'tests/'], executor: $stub);

        $check->run('/tmp/worktree');

        $this->assertContains('@phpstan-ignore', $captured);
        $this->assertContains('src/', $captured);
        $this->assertContains('tests/', $captured);
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
