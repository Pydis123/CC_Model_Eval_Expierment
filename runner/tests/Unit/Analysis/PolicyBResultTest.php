<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\PolicyBResult;
use PHPUnit\Framework\TestCase;

final class PolicyBResultTest extends TestCase
{
    public function testStoresAllFields(): void
    {
        $result = new PolicyBResult(
            expectedTokens: 45_000.0,
            ciLowTokens: 40_000.0,
            ciHighTokens: 50_000.0,
            expectedWallClockS: 250.0,
            ciLowWallClockS: 220.0,
            ciHighWallClockS: 280.0,
            maxTierFailRate: 0.02,
        );

        $this->assertSame(45_000.0, $result->expectedTokens);
        $this->assertSame(40_000.0, $result->ciLowTokens);
        $this->assertSame(50_000.0, $result->ciHighTokens);
        $this->assertSame(250.0, $result->expectedWallClockS);
        $this->assertSame(220.0, $result->ciLowWallClockS);
        $this->assertSame(280.0, $result->ciHighWallClockS);
        $this->assertSame(0.02, $result->maxTierFailRate);
    }
}
