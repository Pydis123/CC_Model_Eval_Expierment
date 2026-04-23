<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use InvalidArgumentException;
use LlmDispatch\Runner\Analysis\PolicyBResult;
use LlmDispatch\Runner\Analysis\PolicyBSimulation;
use PHPUnit\Framework\TestCase;

final class PolicyBSimulationTest extends TestCase
{
    private function makeResult(float $tokens = 1000.0): PolicyBResult
    {
        return new PolicyBResult(
            expectedTokens: $tokens,
            ciLowTokens: $tokens * 0.9,
            ciHighTokens: $tokens * 1.1,
            expectedWallClockS: 100.0,
            ciLowWallClockS: 90.0,
            ciHighWallClockS: 110.0,
            maxTierFailRate: 0.0,
        );
    }

    public function testStoresPerTaskAndOverall(): void
    {
        $perTask = [
            'task-a' => $this->makeResult(1000.0),
            'task-b' => $this->makeResult(2000.0),
        ];
        $overall = $this->makeResult(3000.0);

        $sim = new PolicyBSimulation($perTask, $overall, 1000, 42);

        $this->assertSame(1000.0, $sim->perTask['task-a']->expectedTokens);
        $this->assertSame(2000.0, $sim->perTask['task-b']->expectedTokens);
        $this->assertSame(3000.0, $sim->overall->expectedTokens);
        $this->assertSame(1000, $sim->bootstrapSamples);
        $this->assertSame(42, $sim->bootstrapSeed);
    }

    public function testRejectsEmptyPerTask(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PolicyBSimulation([], $this->makeResult(), 1000, 42);
    }
}
