<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

use LlmDispatch\Runner\State\State;

final class CrashContext
{
    /**
     * @param list<array<string, mixed>> $errors
     * @param array<string, string>      $environment
     */
    public function __construct(
        public readonly string $abortedAt,
        public readonly string $reason,
        public readonly int $runsCompletedBeforeAbort,
        public readonly int $runsRemaining,
        public readonly array $errors,
        public readonly State $stateSnapshot,
        public readonly array $environment,
    ) {}
}
