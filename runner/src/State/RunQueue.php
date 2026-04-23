<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\State;

use LlmDispatch\Runner\Config;

final class RunQueue
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return list<Run>
     */
    public function plan(int $seed): array
    {
        $runs = [];
        foreach ($this->config->taskIds as $taskId) {
            $tuples = $this->tuplesFor($taskId, $seed);
            foreach ($tuples as [$tier, $n]) {
                $runs[] = new Run(
                    runId: $this->runId($taskId, $tier, $n, $seed),
                    taskId: $taskId,
                    modelTier: $tier,
                    n: $n,
                );
            }
        }
        return $runs;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function tuplesFor(string $taskId, int $seed): array
    {
        $combos = [];
        foreach ($this->config->tiers as $tier) {
            for ($n = 1; $n <= $this->config->nReplicates; $n++) {
                $combos[] = [$tier, $n];
            }
        }

        mt_srand($seed ^ crc32($taskId));
        for ($i = count($combos) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$combos[$i], $combos[$j]] = [$combos[$j], $combos[$i]];
        }

        return $combos;
    }

    private function runId(string $taskId, string $tier, int $n, int $seed): string
    {
        $raw = hash('sha256', "{$taskId}|{$tier}|{$n}|{$seed}");
        return substr($raw, 0, 12);
    }
}
