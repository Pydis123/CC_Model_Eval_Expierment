<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\PinCheck;

use LlmDispatch\Runner\PinCheck\PinCheckService;
use PHPUnit\Framework\TestCase;

final class PinCheckServiceTest extends TestCase
{
    public function testMatchingIdsYieldMatchTrue(): void
    {
        $service = new PinCheckService();

        $result = $service->verify('opus', 'claude-opus-4-7', 'claude-opus-4-7');

        $this->assertTrue($result->match);
        $this->assertSame('opus', $result->tier);
        $this->assertSame('claude-opus-4-7', $result->expected);
        $this->assertSame('claude-opus-4-7', $result->actual);
    }

    public function testDiffersYieldMatchFalse(): void
    {
        $service = new PinCheckService();

        $result = $service->verify('opus', 'claude-opus-4-7', 'claude-opus-4-6');

        $this->assertFalse($result->match);
    }

    public function testEmptyActualStillComparesAndReturnsFalse(): void
    {
        $service = new PinCheckService();

        $result = $service->verify('haiku', 'claude-haiku-4-5', '');

        $this->assertFalse($result->match);
        $this->assertSame('', $result->actual);
    }
}
