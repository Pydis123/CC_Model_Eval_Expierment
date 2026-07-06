<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Findings;

final class MatchOutcome
{
    /**
     * @param list<Finding> $unmatched
     * @param list<string> $missedDefectIds
     * @param list<string> $matchedDefectIds
     */
    public function __construct(
        public readonly int $truePositives,
        public readonly int $duplicates,
        public readonly array $unmatched,
        public readonly array $missedDefectIds,
        public readonly array $matchedDefectIds,
    ) {}

    public function recall(int $totalDefects): float
    {
        return $totalDefects > 0 ? $this->truePositives / $totalDefects : 0.0;
    }
}
