<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\PinCheck;

use LlmDispatch\Runner\PinCheck\VerificationResult;
use PHPUnit\Framework\TestCase;

final class VerificationResultTest extends TestCase
{
    public function testMatchingReturnsMatchTrue(): void
    {
        $r = new VerificationResult(tier: 'opus', expected: 'claude-opus-4-7', actual: 'claude-opus-4-7');

        $this->assertTrue($r->match);
    }

    public function testDiffersReturnsMatchFalse(): void
    {
        $r = new VerificationResult(tier: 'opus', expected: 'claude-opus-4-7', actual: 'claude-opus-4-6');

        $this->assertFalse($r->match);
    }

    public function testToArrayExposesAllFields(): void
    {
        $r = new VerificationResult(tier: 'sonnet', expected: 'claude-sonnet-4-6', actual: 'claude-sonnet-4-6');

        $this->assertSame([
            'tier' => 'sonnet',
            'expected' => 'claude-sonnet-4-6',
            'actual' => 'claude-sonnet-4-6',
            'match' => true,
        ], $r->toArray());
    }
}
