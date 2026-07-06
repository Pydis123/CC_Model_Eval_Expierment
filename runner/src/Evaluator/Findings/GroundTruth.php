<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Findings;

final class GroundTruth
{
    /**
     * @param list<SeededDefect> $defects
     */
    public function __construct(
        public readonly array $defects,
    ) {}

    /**
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Ground truth file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read ground truth file: {$path}");
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['defects']) || !is_array($data['defects'])) {
            throw new \InvalidArgumentException("Invalid ground truth format");
        }

        $defects = [];
        foreach ($data['defects'] as $defect) {
            if (!is_array($defect)) {
                throw new \InvalidArgumentException("Defect must be an array");
            }

            if (!isset($defect['id']) || !is_string($defect['id'])) {
                throw new \InvalidArgumentException('Defect missing required field: id');
            }
            if (!isset($defect['file']) || !is_string($defect['file'])) {
                throw new \InvalidArgumentException('Defect missing required field: file');
            }
            if (!isset($defect['defect_class']) || !is_string($defect['defect_class'])) {
                throw new \InvalidArgumentException('Defect missing required field: defect_class');
            }
            if (!isset($defect['line']) || !is_int($defect['line'])) {
                throw new \InvalidArgumentException('Defect missing required field: line');
            }

            $spanStart = isset($defect['span_start']) && is_int($defect['span_start']) ? $defect['span_start'] : null;
            $spanEnd = isset($defect['span_end']) && is_int($defect['span_end']) ? $defect['span_end'] : null;

            $defects[] = new SeededDefect($defect['id'], $defect['file'], $defect['defect_class'], $defect['line'], $spanStart, $spanEnd);
        }

        return new self($defects);
    }
}
