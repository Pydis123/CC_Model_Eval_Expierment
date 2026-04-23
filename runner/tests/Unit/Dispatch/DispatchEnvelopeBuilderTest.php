<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\DispatchEnvelopeBuilder;
use PHPUnit\Framework\TestCase;

final class DispatchEnvelopeBuilderTest extends TestCase
{
    public function testIterationOneReturnsRawPrompt(): void
    {
        $envelope = (new DispatchEnvelopeBuilder())->build(
            rawPrompt: 'Implement feature X.',
            priorFailedChecksSummary: null,
        );

        $this->assertSame('Implement feature X.', $envelope);
    }

    public function testLaterIterationAppendsFailedChecks(): void
    {
        $envelope = (new DispatchEnvelopeBuilder())->build(
            rawPrompt: 'Implement feature X.',
            priorFailedChecksSummary: "- phpunit: 2 tests failed\n- lint: type mismatch",
        );

        $this->assertStringStartsWith('Implement feature X.', $envelope);
        $this->assertStringContainsString('---', $envelope);
        $this->assertStringContainsString('Previous attempt failed the evaluator', $envelope);
        $this->assertStringContainsString('- phpunit: 2 tests failed', $envelope);
        $this->assertStringContainsString('- lint: type mismatch', $envelope);
        $this->assertStringContainsString('Fix the failing checks.', $envelope);
    }

    public function testEmptySummaryStillTreatedAsRetry(): void
    {
        $envelope = (new DispatchEnvelopeBuilder())->build(
            rawPrompt: 'X',
            priorFailedChecksSummary: '',
        );

        $this->assertStringContainsString('Previous attempt failed', $envelope);
    }
}
