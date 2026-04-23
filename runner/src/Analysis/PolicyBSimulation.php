<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

use InvalidArgumentException;

final class PolicyBSimulation
{
    /**
     * @param array<string, PolicyBResult> $perTask
     */
    public function __construct(
        public readonly array $perTask,
        public readonly PolicyBResult $overall,
        public readonly int $bootstrapSamples,
        public readonly int $bootstrapSeed,
    ) {
        if ($perTask === []) {
            throw new InvalidArgumentException('PolicyBSimulation requires at least one per-task result');
        }
    }
}
