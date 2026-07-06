<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Findings;

final class Finding
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $defectClass,
        public readonly string $explanation,
    ) {}

    /**
     * @param array<string, mixed> $d
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $d): self
    {
        if (!isset($d['file']) || !is_string($d['file'])) {
            throw new \InvalidArgumentException('Missing or invalid "file" in finding');
        }
        if (!isset($d['line']) || !is_int($d['line'])) {
            throw new \InvalidArgumentException('Missing or invalid "line" in finding');
        }
        if (!isset($d['defect_class']) || !is_string($d['defect_class'])) {
            throw new \InvalidArgumentException('Missing or invalid "defect_class" in finding');
        }

        $explanation = isset($d['explanation']) && is_string($d['explanation']) ? $d['explanation'] : '';

        return new self($d['file'], $d['line'], $d['defect_class'], $explanation);
    }
}
