<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\ConnectivityChecker;
use PHPUnit\Framework\TestCase;

final class ConnectivityCheckerTest extends TestCase
{
    public function testIsOnlineTrueWhenCanConnectToServer(): void
    {
        // Start a local listener
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertIsResource($server, "Failed to create test server: $errstr");

        // Parse the assigned port
        $sockName = stream_socket_get_name($server, false);
        $this->assertIsString($sockName);
        $parts = explode(':', $sockName);
        $port = (int) $parts[1];
        $this->assertGreaterThan(0, $port);

        $checker = new ConnectivityChecker('127.0.0.1', $port, 2.0);
        $this->assertTrue($checker->isOnline());

        fclose($server);
    }

    public function testIsOnlineFalseWhenCannotConnectToServer(): void
    {
        // Use a port that is unlikely to be open and close it quickly
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertIsResource($server);

        $sockName = stream_socket_get_name($server, false);
        $this->assertIsString($sockName);
        $parts = explode(':', $sockName);
        $port = (int) $parts[1];

        fclose($server);

        // Now the port is closed, should fail quickly
        $checker = new ConnectivityChecker('127.0.0.1', $port, 0.5);
        $this->assertFalse($checker->isOnline());
    }
}
