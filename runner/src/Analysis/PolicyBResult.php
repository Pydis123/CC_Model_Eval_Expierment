<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

final class PolicyBResult
{
    public function __construct(
        public readonly float $expectedTokens,
        public readonly float $ciLowTokens,
        public readonly float $ciHighTokens,
        public readonly float $expectedWallClockS,
        public readonly float $ciLowWallClockS,
        public readonly float $ciHighWallClockS,
        public readonly float $maxTierFailRate,
    ) {}
}
