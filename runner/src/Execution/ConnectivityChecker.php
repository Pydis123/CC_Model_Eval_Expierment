<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

class ConnectivityChecker
{
    public function __construct(
        private readonly string $host = 'api.anthropic.com',
        private readonly int $port = 443,
        private readonly float $timeoutS = 5.0,
    ) {}

    public function isOnline(): bool
    {
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeoutS);
        if ($fp === false) {
            return false;
        }
        fclose($fp);
        return true;
    }
}
