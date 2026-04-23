<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator;

use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\EvaluationResult;
use PHPUnit\Framework\TestCase;

final class EvaluationResultTest extends TestCase
{
    public function testOutcomePassedWhenAllChecksPass(): void
    {
        $r = new EvaluationResult([
            new CheckResult('phpunit', true, []),
            new CheckResult('lint', true, []),
        ], 10.5);

        $this->assertSame('passed', $r->outcome);
        $this->assertSame(10.5, $r->wallClockS);
    }

    public function testOutcomeFailedWhenAnyCheckFails(): void
    {
        $r = new EvaluationResult([
            new CheckResult('phpunit', true, []),
            new CheckResult('lint', false, ['errors' => ['oops']]),
        ], 11.0);

        $this->assertSame('failed', $r->outcome);
    }

    public function testOutcomePassedWithNoChecks(): void
    {
        $r = new EvaluationResult([], 0.1);

        $this->assertSame('passed', $r->outcome);
    }

    public function testToArraySerializesChecks(): void
    {
        $r = new EvaluationResult([
            (new CheckResult('phpunit', true, ['tests' => 1]))->withWallClock(3.0),
        ], 3.2);

        $this->assertSame([
            'outcome' => 'passed',
            'wall_clock_s' => 3.2,
            'checks' => [
                [
                    'type' => 'phpunit',
                    'passed' => true,
                    'wall_clock_s' => 3.0,
                    'details' => ['tests' => 1],
                ],
            ],
        ], $r->toArray());
    }
}
