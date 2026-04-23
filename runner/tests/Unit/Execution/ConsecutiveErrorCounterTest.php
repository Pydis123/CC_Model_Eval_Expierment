<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\ConsecutiveErrorCounter;
use PHPUnit\Framework\TestCase;

final class ConsecutiveErrorCounterTest extends TestCase
{
    public function testStartsAtZero(): void
    {
        $c = new ConsecutiveErrorCounter(threshold: 5);
        $this->assertSame(0, $c->count());
        $this->assertFalse($c->shouldAbort());
    }

    public function testRegisterErrorIncrements(): void
    {
        $c = new ConsecutiveErrorCounter(threshold: 5);
        $c->recordUnexpectedError();
        $c->recordUnexpectedError();
        $this->assertSame(2, $c->count());
    }

    public function testRegisterRunResets(): void
    {
        $c = new ConsecutiveErrorCounter(threshold: 5);
        $c->recordUnexpectedError();
        $c->recordUnexpectedError();
        $c->recordRegisteredRun();
        $this->assertSame(0, $c->count());
    }

    public function testShouldAbortAtThreshold(): void
    {
        $c = new ConsecutiveErrorCounter(threshold: 5);
        for ($i = 0; $i < 5; $i++) {
            $c->recordUnexpectedError();
        }
        $this->assertTrue($c->shouldAbort());
    }

    public function testShouldNotAbortBelowThreshold(): void
    {
        $c = new ConsecutiveErrorCounter(threshold: 5);
        for ($i = 0; $i < 4; $i++) {
            $c->recordUnexpectedError();
        }
        $this->assertFalse($c->shouldAbort());
    }
}
