<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

final class OfflineGate
{
    /**
     * @param callable(int): void $sleep
     */
    public function __construct(
        private readonly ConnectivityChecker $checker,
        private readonly ProgressLogger $logger,
        private readonly string $pauseSentinelPath,
        private readonly mixed $sleep,
    ) {}

    public function waitUntilOnline(): bool
    {
        if ($this->checker->isOnline()) {
            return true;
        }

        $this->logger->log('offline detected; waiting for connectivity');

        $delay = 15;
        $checks = 0;

        while (true) {
            // Check for pause sentinel before sleeping
            if ($this->pauseSentinelPath !== '' && file_exists($this->pauseSentinelPath)) {
                $this->logger->log('PAUSE sentinel found while offline; stopping');
                return false;
            }

            ($this->sleep)($delay);
            $checks++;

            if ($this->checker->isOnline()) {
                $this->logger->log("connectivity restored after {$checks} checks");
                return true;
            }

            // Backoff: 15, 30, 60, 60, ...
            if ($delay < 60) {
                $delay *= 2;
                if ($delay > 60) {
                    $delay = 60;
                }
            }
        }
    }
}
