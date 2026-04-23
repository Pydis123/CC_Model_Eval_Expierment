<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator;

use LlmDispatch\Runner\Evaluator\CheckResult;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    public function testCarriesPassedTypeAndDetails(): void
    {
        $r = new CheckResult(type: 'phpunit', passed: true, details: ['tests' => 74]);

        $this->assertSame('phpunit', $r->type);
        $this->assertTrue($r->passed);
        $this->assertSame(['tests' => 74], $r->details);
    }

    public function testWallClockDefaultsToZero(): void
    {
        $r = new CheckResult(type: 'lint', passed: false, details: []);

        $this->assertSame(0.0, $r->wallClockS);
    }

    public function testWithWallClockReturnsNewInstance(): void
    {
        $r1 = new CheckResult(type: 'lint', passed: true, details: []);
        $r2 = $r1->withWallClock(4.2);

        $this->assertSame(0.0, $r1->wallClockS);
        $this->assertSame(4.2, $r2->wallClockS);
        $this->assertNotSame($r1, $r2);
    }

    public function testToArrayRoundtrip(): void
    {
        $r = (new CheckResult(type: 'phpunit', passed: true, details: ['x' => 1]))
            ->withWallClock(2.5);

        $this->assertSame([
            'type' => 'phpunit',
            'passed' => true,
            'wall_clock_s' => 2.5,
            'details' => ['x' => 1],
        ], $r->toArray());
    }
}
