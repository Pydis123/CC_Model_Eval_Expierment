<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\EvaluateCommand;
use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\Evaluator;
use PHPUnit\Framework\TestCase;

final class EvaluateCommandTest extends TestCase
{
    private string $tmpTasksDir;

    protected function setUp(): void
    {
        $this->tmpTasksDir = sys_get_temp_dir() . '/tasks_' . uniqid();
        mkdir($this->tmpTasksDir);
        file_put_contents($this->tmpTasksDir . '/my-task.json', json_encode([
            'id' => 'my-task',
            'success_criteria' => [['type' => 'pass']],
        ]));
        file_put_contents($this->tmpTasksDir . '/fail-task.json', json_encode([
            'id' => 'fail-task',
            'success_criteria' => [['type' => 'fail']],
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpTasksDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpTasksDir);
    }

    public function testEvaluatesAndExitsZeroOnPass(): void
    {
        $evaluator = new Evaluator([
            'pass' => static fn(array $c) => new class implements CheckInterface {
                public function run(string $wt): CheckResult { return new CheckResult('pass', true, []); }
            },
        ]);

        $command = new EvaluateCommand($this->tmpTasksDir, $evaluator);

        ob_start();
        $exit = $command->run(['--task=my-task', '--worktree=/tmp/wt']);
        $out = ob_get_clean();

        $this->assertSame(0, $exit);
        $json = json_decode($out, true);
        $this->assertSame('passed', $json['outcome']);
        $this->assertCount(1, $json['checks']);
    }

    public function testExitsOneOnFailure(): void
    {
        $evaluator = new Evaluator([
            'fail' => static fn(array $c) => new class implements CheckInterface {
                public function run(string $wt): CheckResult { return new CheckResult('fail', false, []); }
            },
        ]);

        $command = new EvaluateCommand($this->tmpTasksDir, $evaluator);

        ob_start();
        $exit = $command->run(['--task=fail-task', '--worktree=/tmp/wt']);
        ob_end_clean();

        $this->assertSame(1, $exit);
    }

    public function testExitsThreeWhenTaskFileMissing(): void
    {
        $evaluator = new Evaluator([]);
        $command = new EvaluateCommand($this->tmpTasksDir, $evaluator);

        ob_start();
        $exit = $command->run(['--task=nonexistent', '--worktree=/tmp/wt']);
        ob_end_clean();

        $this->assertSame(3, $exit);
    }

    public function testExitsTwoOnMissingArgs(): void
    {
        $command = new EvaluateCommand($this->tmpTasksDir, new Evaluator([]));

        ob_start();
        $exit = $command->run(['--task=my-task']);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }
}
