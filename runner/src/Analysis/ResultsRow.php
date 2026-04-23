<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

use InvalidArgumentException;

final class ResultsRow
{
    private const VALID_TIERS = ['haiku', 'sonnet', 'opus'];
    private const VALID_OUTCOMES = ['passed', 'failed'];

    public function __construct(
        public readonly string $runId,
        public readonly string $taskId,
        public readonly string $modelTier,
        public readonly string $modelId,
        public readonly int $n,
        public readonly string $outcome,
        public readonly int $iterationsUsed,
        public readonly int $tokensSubagentIn,
        public readonly int $tokensSubagentOut,
        public readonly int $tokensPmOverhead,
        public readonly int $wallClockSubagentS,
        public readonly int $wallClockTotalS,
        public readonly string $timestampStart,
        public readonly string $timestampEnd,
    ) {
        if (!in_array($modelTier, self::VALID_TIERS, true)) {
            throw new InvalidArgumentException("Invalid model_tier: {$modelTier}");
        }
        if (!in_array($outcome, self::VALID_OUTCOMES, true)) {
            throw new InvalidArgumentException("Invalid outcome: {$outcome}");
        }
    }

    public function tokensSubagentTotal(): int
    {
        return $this->tokensSubagentIn + $this->tokensSubagentOut;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $required = [
            'run_id', 'task_id', 'model_tier', 'model_id', 'n', 'outcome',
            'iterations_used', 'tokens_subagent_in', 'tokens_subagent_out',
            'tokens_pm_overhead', 'wall_clock_subagent_s', 'wall_clock_total_s',
            'timestamp_start', 'timestamp_end',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required field: {$key}");
            }
        }

        return new self(
            runId: (string) $data['run_id'],
            taskId: (string) $data['task_id'],
            modelTier: (string) $data['model_tier'],
            modelId: (string) $data['model_id'],
            n: (int) $data['n'],
            outcome: (string) $data['outcome'],
            iterationsUsed: (int) $data['iterations_used'],
            tokensSubagentIn: (int) $data['tokens_subagent_in'],
            tokensSubagentOut: (int) $data['tokens_subagent_out'],
            tokensPmOverhead: (int) $data['tokens_pm_overhead'],
            wallClockSubagentS: (int) $data['wall_clock_subagent_s'],
            wallClockTotalS: (int) $data['wall_clock_total_s'],
            timestampStart: (string) $data['timestamp_start'],
            timestampEnd: (string) $data['timestamp_end'],
        );
    }
}
