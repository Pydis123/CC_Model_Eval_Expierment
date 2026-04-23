<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\FailedChecksSummarizer;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\EvaluationResult;
use PHPUnit\Framework\TestCase;

final class FailedChecksSummarizerTest extends TestCase
{
    public function testSummarizesSingleFailedCheck(): void
    {
        $eval = new EvaluationResult([
            new CheckResult('phpunit', false, ['error' => '2 tests failed']),
        ], 1.0);

        $summary = (new FailedChecksSummarizer())->summarize($eval);

        $this->assertStringContainsString('- phpunit:', $summary);
        $this->assertStringContainsString('2 tests failed', $summary);
    }

    public function testSummarizesMultipleFailedChecks(): void
    {
        $eval = new EvaluationResult([
            new CheckResult('phpunit', false, ['error' => 'tests failed']),
            new CheckResult('lint', true, []),
            new CheckResult('query_count', false, ['error' => 'too many queries']),
        ], 1.0);

        $summary = (new FailedChecksSummarizer())->summarize($eval);

        $this->assertStringContainsString('- phpunit:', $summary);
        $this->assertStringContainsString('- query_count:', $summary);
        $this->assertStringNotContainsString('- lint:', $summary);
    }

    public function testReturnsEmptyWhenAllPassed(): void
    {
        $eval = new EvaluationResult([
            new CheckResult('phpunit', true, []),
            new CheckResult('lint', true, []),
        ], 1.0);

        $summary = (new FailedChecksSummarizer())->summarize($eval);

        $this->assertSame('', $summary);
    }

    public function testFallsBackToGenericMessageWhenNoError(): void
    {
        $eval = new EvaluationResult([
            new CheckResult('phpunit', false, []),
        ], 1.0);

        $summary = (new FailedChecksSummarizer())->summarize($eval);

        $this->assertStringContainsString('- phpunit:', $summary);
        $this->assertStringContainsString('check failed', $summary);
    }
}
