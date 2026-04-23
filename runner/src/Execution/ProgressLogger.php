<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

use DateTimeImmutable;
use DateTimeZone;

final class ProgressLogger
{
    public function __construct(private readonly string $logPath) {}

    public function log(string $message): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $line = sprintf('[%s] %s', $ts, $message);

        echo $line . "\n";
        file_put_contents($this->logPath, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
