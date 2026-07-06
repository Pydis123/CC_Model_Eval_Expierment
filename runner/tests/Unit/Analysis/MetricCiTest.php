<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use InvalidArgumentException;
use LlmDispatch\Runner\Analysis\MetricCi;
use PHPUnit\Framework\TestCase;

final class MetricCiTest extends TestCase
{
    public function testConstantValuesProduceDegenerateInterval(): void
    {
        $result = MetricCi::bootstrap([1.0, 1.0, 1.0], 100, 42);

        $this->assertSame(1.0, $result['low']);
        $this->assertSame(1.0, $result['high']);
    }

    public function testDeterministicForSameSeed(): void
    {
        $first = MetricCi::bootstrap([0.2, 0.4, 0.9, 1.0], 100, 42);
        $second = MetricCi::bootstrap([0.2, 0.4, 0.9, 1.0], 100, 42);

        $this->assertSame($first, $second);
    }

    public function testRejectsEmptyValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MetricCi::bootstrap([], 100, 42);
    }

    public function testRejectsNonPositiveSamples(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MetricCi::bootstrap([1.0, 2.0], 0, 42);
    }
}
