<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

final class LoadedTask
{
    /**
     * @param array<string, mixed> $taskDef
     */
    public function __construct(
        public readonly string $taskId,
        public readonly string $prompt,
        public readonly int $maxIterations,
        public readonly int $maxWallClockS,
        public readonly array $taskDef,
    ) {}
}
