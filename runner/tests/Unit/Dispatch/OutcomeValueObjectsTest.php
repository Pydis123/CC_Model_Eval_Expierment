<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\IterationOutcome;
use LlmDispatch\Runner\Dispatch\RunOutcome;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\EvaluationResult;
use PHPUnit\Framework\TestCase;

final class OutcomeValueObjectsTest extends TestCase
{
    private function passingEvaluation(): EvaluationResult
    {
        return new EvaluationResult([new CheckResult('phpunit', true, [])], 1.0);
    }

    private function failingEvaluation(): EvaluationResult
    {
        return new EvaluationResult([new CheckResult('phpunit', false, ['error' => 'x'])], 1.0);
    }

    public function testIterationOutcomeStoresFields(): void
    {
        $it = new IterationOutcome(
            index: 1,
            promptUsed: 'do the thing',
            tokensIn: 100,
            tokensOut: 50,
            wallClockS: 12,
            modelIdReported: 'claude-haiku-4-5-20251001',
            costUsd: 0.01,
            evaluation: $this->passingEvaluation(),
            errorCategory: null,
        );

        $this->assertSame(1, $it->index);
        $this->assertSame(150, $it->totalTokens());
        $this->assertSame('passed', $it->evaluatorOutcome());
        $this->assertNull($it->errorCategory);
    }

    public function testIterationOutcomeEvaluatorOutcomeFromFailingEval(): void
    {
        $it = new IterationOutcome(
            index: 1,
            promptUsed: 'p',
            tokensIn: 1,
            tokensOut: 1,
            wallClockS: 1,
            modelIdReported: 'm',
            costUsd: 0.0,
            evaluation: $this->failingEvaluation(),
            errorCategory: null,
        );

        $this->assertSame('failed', $it->evaluatorOutcome());
    }

    public function testIterationOutcomeErrorCategoryOverridesOutcome(): void
    {
        $it = new IterationOutcome(
            index: 1,
            promptUsed: 'p',
            tokensIn: 0,
            tokensOut: 0,
            wallClockS: 0,
            modelIdReported: '',
            costUsd: 0.0,
            evaluation: null,
            errorCategory: 'claude_cli_is_error',
        );

        $this->assertSame('error', $it->evaluatorOutcome());
        $this->assertSame('claude_cli_is_error', $it->errorCategory);
    }

    public function testRunOutcomePassedWhenLastIterationPassed(): void
    {
        $iter = new IterationOutcome(
            1, 'p', 10, 5, 1, 'm', 0.0, $this->passingEvaluation(), null,
        );

        $outcome = new RunOutcome(
            iterations: [$iter],
            finalOutcome: 'passed',
            errorCategory: null,
        );

        $this->assertSame('passed', $outcome->finalOutcome);
        $this->assertSame(1, $outcome->iterationsUsed());
        $this->assertSame(10, $outcome->totalTokensIn());
        $this->assertSame(5, $outcome->totalTokensOut());
        $this->assertSame(1, $outcome->totalWallClockS());
    }

    public function testRunOutcomeFailedAfterThreeFailures(): void
    {
        $a = new IterationOutcome(1, 'p', 10, 5, 1, 'm', 0.0, $this->failingEvaluation(), null);
        $b = new IterationOutcome(2, 'p', 20, 10, 2, 'm', 0.0, $this->failingEvaluation(), null);
        $c = new IterationOutcome(3, 'p', 30, 15, 3, 'm', 0.0, $this->failingEvaluation(), null);

        $outcome = new RunOutcome(
            iterations: [$a, $b, $c],
            finalOutcome: 'failed',
            errorCategory: null,
        );

        $this->assertSame('failed', $outcome->finalOutcome);
        $this->assertSame(3, $outcome->iterationsUsed());
        $this->assertSame(60, $outcome->totalTokensIn());
        $this->assertSame(30, $outcome->totalTokensOut());
        $this->assertSame(6, $outcome->totalWallClockS());
    }

    public function testRunOutcomeErrorPropagatesCategory(): void
    {
        $it = new IterationOutcome(
            1, 'p', 0, 0, 0, '', 0.0, null, 'claude_cli_malformed_json',
        );

        $outcome = new RunOutcome(
            iterations: [$it],
            finalOutcome: 'error',
            errorCategory: 'claude_cli_malformed_json',
        );

        $this->assertSame('error', $outcome->finalOutcome);
        $this->assertSame('claude_cli_malformed_json', $outcome->errorCategory);
    }

    public function testRunOutcomeLastEvaluationReturnsLastNonNull(): void
    {
        $a = new IterationOutcome(1, 'p', 10, 5, 1, 'm', 0.0, $this->failingEvaluation(), null);
        $b = new IterationOutcome(2, 'p', 20, 10, 2, 'm', 0.0, $this->passingEvaluation(), null);

        $outcome = new RunOutcome([$a, $b], 'passed', null);

        $this->assertSame($b->evaluation, $outcome->lastEvaluation());
    }
}
