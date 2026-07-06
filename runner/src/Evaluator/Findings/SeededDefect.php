<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Findings;

final class SeededDefect
{
    public function __construct(
        public readonly string $id,
        public readonly string $file,
        public readonly string $defectClass,
        public readonly int $line,
        public readonly ?int $spanStart,
        public readonly ?int $spanEnd,
    ) {}
}
