<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\SwapDetector;
use PHPUnit\Framework\TestCase;

final class SwapDetectorTest extends TestCase
{
    public function testHaltsAfterThresholdConsecutiveUnexpectedIds(): void
    {
        $d = new SwapDetector(3);
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $this->assertFalse($d->shouldHalt());
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $this->assertTrue($d->shouldHalt());
        $this->assertStringContainsString('fable', (string) $d->haltReason());
    }

    public function testExpectedIdResetsStreak(): void
    {
        $d = new SwapDetector(3);
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $d->record('fable', 'claude-fable-5', 'claude-fable-5');
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $this->assertFalse($d->shouldHalt());
    }

    public function testDifferentUnexpectedIdsDoNotAccumulate(): void
    {
        $d = new SwapDetector(3);
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $d->record('fable', 'claude-sonnet-5', 'claude-fable-5');
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $this->assertFalse($d->shouldHalt());
    }

    public function testInterveningCleanRunOnOtherTierDoesNotResetStreak(): void
    {
        $d = new SwapDetector(3);
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $d->record('haiku', 'claude-haiku-4-5-20251001', 'claude-haiku-4-5-20251001');
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $d->record('fable', 'claude-opus-4-8', 'claude-fable-5');
        $this->assertTrue($d->shouldHalt());
    }
}
