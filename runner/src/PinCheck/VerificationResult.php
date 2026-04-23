<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\PinCheck;

final class VerificationResult
{
    public readonly bool $match;

    public function __construct(
        public readonly string $tier,
        public readonly string $expected,
        public readonly string $actual,
    ) {
        $this->match = $expected === $actual;
    }

    /**
     * @return array{tier: string, expected: string, actual: string, match: bool}
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'match' => $this->match,
        ];
    }
}
