<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\ConnectivityChecker;
use LlmDispatch\Runner\Execution\OfflineGate;
use LlmDispatch\Runner\Execution\ProgressLogger;
use PHPUnit\Framework\TestCase;

final class OfflineGateTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/offline_gate_' . uniqid();
        mkdir($base, 0777, true);
        $this->logPath = $base . '/test.log';
    }

    private function makeStubChecker(array $onlineSequence): ConnectivityChecker
    {
        return new class($onlineSequence) extends ConnectivityChecker {
            private int $index = 0;

            /** @param list<bool> $onlineSequence */
            public function __construct(private readonly array $onlineSequence)
            {
                parent::__construct();
            }

            public function isOnline(): bool
            {
                $result = $this->onlineSequence[$this->index] ?? true;
                $this->index++;
                return $result;
            }
        };
    }

    public function testOnlineImmediatelyReturnsTrueWithoutSleeping(): void
    {
        $checker = $this->makeStubChecker([true]);
        $logger = new ProgressLogger($this->logPath);
        $sleeps = [];
        $sleep = static function(int $s) use (&$sleeps): void {
            $sleeps[] = $s;
        };

        $gate = new OfflineGate($checker, $logger, '', $sleep);
        ob_start();
        $result = $gate->waitUntilOnline();
        ob_end_clean();

        $this->assertTrue($result);
        $this->assertCount(0, $sleeps);
    }

    public function testOfflineFourTimesThenOnlineReturnsTrue(): void
    {
        $checker = $this->makeStubChecker([false, false, false, false, true]);
        $logger = new ProgressLogger($this->logPath);
        $sleeps = [];
        $sleep = static function(int $s) use (&$sleeps): void {
            $sleeps[] = $s;
        };

        $gate = new OfflineGate($checker, $logger, '', $sleep);
        ob_start();
        $result = $gate->waitUntilOnline();
        ob_end_clean();

        $this->assertTrue($result);
        $this->assertSame([15, 30, 60, 60], $sleeps);
    }

    public function testPauseSentinelDetectionReturnsFalse(): void
    {
        $base = sys_get_temp_dir() . '/offline_gate_' . uniqid();
        mkdir($base, 0777, true);
        $sentinelPath = $base . '/PAUSE';

        // Create the sentinel file before the call
        touch($sentinelPath);

        $checker = $this->makeStubChecker([false, false, false]); // Always offline
        $logger = new ProgressLogger($this->logPath);
        $sleeps = [];
        $sleep = static function(int $s) use (&$sleeps): void {
            $sleeps[] = $s;
        };

        $gate = new OfflineGate($checker, $logger, $sentinelPath, $sleep);
        ob_start();
        $result = $gate->waitUntilOnline();
        ob_end_clean();

        $this->assertFalse($result);
        $this->assertLessThanOrEqual(1, count($sleeps), 'Should detect sentinel before sleeping many times');
    }
}
