<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\DispatchDisposition;
use LlmDispatch\Runner\Dispatch\IterationOutcome;
use LlmDispatch\Runner\Dispatch\RunOutcome;
use PHPUnit\Framework\TestCase;

final class DispatchDispositionTest extends TestCase
{
    public function testCompletedWhenReportedMatchesExpected(): void
    {
        $outcome = new RunOutcome([$this->iter('claude-fable-5', 'Done.')], 'passed', null);
        $this->assertSame('completed', DispatchDisposition::classify('claude-fable-5', $outcome));
    }

    public function testReroutedWhenReportedDiffers(): void
    {
        $outcome = new RunOutcome([$this->iter('claude-opus-4-8', 'Done.')], 'passed', null);
        $this->assertSame('model_rerouted', DispatchDisposition::classify('claude-fable-5', $outcome));
    }

    public function testRefusalDetected(): void
    {
        $outcome = new RunOutcome([$this->iter('claude-fable-5', "I can't help with that request.")], 'failed', null);
        $this->assertSame('refused_in_band', DispatchDisposition::classify('claude-fable-5', $outcome));
    }

    public function testErrorOutcome(): void
    {
        $outcome = new RunOutcome([$this->iter('', '')], 'error', 'claude_cli_is_error');
        $this->assertSame('error', DispatchDisposition::classify('claude-fable-5', $outcome));
    }

    public function testEmptyReportedIdIsNotRerouted(): void
    {
        $outcome = new RunOutcome([$this->iter('', 'Done.')], 'passed', null);
        $this->assertSame('completed', DispatchDisposition::classify('claude-fable-5', $outcome));
    }

    private function iter(string $reportedId, string $text): IterationOutcome
    {
        return new IterationOutcome(1, 'p', 1, 1, 1, $reportedId, 0.0, null, null, $text);
    }
}
