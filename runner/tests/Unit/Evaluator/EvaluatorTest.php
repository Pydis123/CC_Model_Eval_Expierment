<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\Evaluator;
use PHPUnit\Framework\TestCase;

final class EvaluatorTest extends TestCase
{
    public function testEvaluatesEachCriterionViaFactory(): void
    {
        $factories = [
            'foo' => static fn(array $c) => new class implements CheckInterface {
                public function run(string $wt): CheckResult
                {
                    return new CheckResult('foo', true, ['got' => 'here']);
                }
            },
        ];

        $evaluator = new Evaluator($factories);

        $result = $evaluator->evaluate(
            taskDef: ['success_criteria' => [['type' => 'foo']]],
            worktreePath: '/tmp/wt',
        );

        $this->assertSame('passed', $result->outcome);
        $this->assertCount(1, $result->checks);
        $this->assertSame('foo', $result->checks[0]->type);
    }

    public function testAggregatesMultipleCriteria(): void
    {
        $factories = [
            'always_pass' => static fn(array $c) => new class implements CheckInterface {
                public function run(string $wt): CheckResult
                {
                    return new CheckResult('always_pass', true, []);
                }
            },
            'always_fail' => static fn(array $c) => new class implements CheckInterface {
                public function run(string $wt): CheckResult
                {
                    return new CheckResult('always_fail', false, []);
                }
            },
        ];

        $evaluator = new Evaluator($factories);

        $result = $evaluator->evaluate(
            taskDef: [
                'success_criteria' => [
                    ['type' => 'always_pass'],
                    ['type' => 'always_fail'],
                ],
            ],
            worktreePath: '/tmp/wt',
        );

        $this->assertSame('failed', $result->outcome);
        $this->assertCount(2, $result->checks);
    }

    public function testPassesCriterionConfigToFactory(): void
    {
        $capturedConfig = null;
        $factories = [
            'foo' => function (array $c) use (&$capturedConfig) {
                $capturedConfig = $c;
                return new class implements CheckInterface {
                    public function run(string $wt): CheckResult
                    {
                        return new CheckResult('foo', true, []);
                    }
                };
            },
        ];

        $evaluator = new Evaluator($factories);

        $evaluator->evaluate(
            taskDef: ['success_criteria' => [['type' => 'foo', 'threshold' => 42]]],
            worktreePath: '/tmp/wt',
        );

        $this->assertSame(['type' => 'foo', 'threshold' => 42], $capturedConfig);
    }

    public function testThrowsOnUnknownCriterionType(): void
    {
        $evaluator = new Evaluator([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown check type: bogus/i');

        $evaluator->evaluate(
            taskDef: ['success_criteria' => [['type' => 'bogus']]],
            worktreePath: '/tmp/wt',
        );
    }

    public function testRecordsPerCheckWallClock(): void
    {
        $factories = [
            'slow' => static fn(array $c) => new class implements CheckInterface {
                public function run(string $wt): CheckResult
                {
                    usleep(10_000);
                    return new CheckResult('slow', true, []);
                }
            },
        ];

        $evaluator = new Evaluator($factories);

        $result = $evaluator->evaluate(
            taskDef: ['success_criteria' => [['type' => 'slow']]],
            worktreePath: '/tmp/wt',
        );

        $this->assertGreaterThan(0.005, $result->checks[0]->wallClockS);
        $this->assertGreaterThan(0.005, $result->wallClockS);
    }
}
