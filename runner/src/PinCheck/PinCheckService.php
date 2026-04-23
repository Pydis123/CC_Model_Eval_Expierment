<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\PinCheck;

final class PinCheckService
{
    public function verify(string $tier, string $expected, string $actual): VerificationResult
    {
        return new VerificationResult($tier, $expected, $actual);
    }
}
