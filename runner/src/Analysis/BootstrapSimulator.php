<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

final class BootstrapSimulator
{
    /**
     * @param array<string, array<string, CellStats>> $matrix
     * @param list<string> $taskIds
     * @param list<string> $tiers   tiers in cheapest-first order (e.g. ['haiku','sonnet','opus'])
     */
    public function simulate(
        array $matrix,
        array $taskIds,
        array $tiers,
        int $samples,
        int $seed,
    ): PolicyBSimulation {
        mt_srand($seed);

        /** @var array<string, list<array{tokens: int, wall: int, failed: bool}>> $perTaskIterations */
        $perTaskIterations = [];
        /** @var list<array{tokens: int, wall: int, failed: int}> $overallIterations */
        $overallIterations = [];

        foreach ($taskIds as $taskId) {
            $perTaskIterations[$taskId] = [];
        }

        for ($i = 0; $i < $samples; $i++) {
            $overallTokens = 0;
            $overallWall = 0;
            $overallFailedCount = 0;

            foreach ($taskIds as $taskId) {
                $taskTokens = 0;
                $taskWall = 0;
                $taskFailed = true;

                foreach ($tiers as $tier) {
                    $cell = $matrix[$taskId][$tier];
                    $pick = $cell->runs[mt_rand(0, $cell->nRuns - 1)];
                    $taskTokens += $pick->tokensSubagentTotal();
                    $taskWall += $pick->wallClockSubagentS;
                    if ($pick->outcome === 'passed') {
                        $taskFailed = false;
                        break;
                    }
                }

                $perTaskIterations[$taskId][] = [
                    'tokens' => $taskTokens,
                    'wall' => $taskWall,
                    'failed' => $taskFailed,
                ];

                $overallTokens += $taskTokens;
                $overallWall += $taskWall;
                if ($taskFailed) {
                    $overallFailedCount++;
                }
            }

            $overallIterations[] = [
                'tokens' => $overallTokens,
                'wall' => $overallWall,
                'failed' => $overallFailedCount,
            ];
        }

        $perTaskResults = [];
        foreach ($taskIds as $taskId) {
            $perTaskResults[$taskId] = $this->summarize($perTaskIterations[$taskId]);
        }

        $overallResult = $this->summarizeOverall($overallIterations, count($taskIds));

        return new PolicyBSimulation($perTaskResults, $overallResult, $samples, $seed);
    }

    /**
     * @param list<array{tokens: int, wall: int, failed: bool}> $iterations
     */
    private function summarize(array $iterations): PolicyBResult
    {
        $tokens = array_map(static fn(array $it) => $it['tokens'], $iterations);
        $walls = array_map(static fn(array $it) => $it['wall'], $iterations);
        $failed = array_map(static fn(array $it) => $it['failed'] ? 1 : 0, $iterations);

        return new PolicyBResult(
            expectedTokens: $this->mean($tokens),
            ciLowTokens: $this->percentile($tokens, 2.5),
            ciHighTokens: $this->percentile($tokens, 97.5),
            expectedWallClockS: $this->mean($walls),
            ciLowWallClockS: $this->percentile($walls, 2.5),
            ciHighWallClockS: $this->percentile($walls, 97.5),
            maxTierFailRate: $this->mean($failed),
        );
    }

    /**
     * @param list<array{tokens: int, wall: int, failed: int}> $iterations
     */
    private function summarizeOverall(array $iterations, int $nTasks): PolicyBResult
    {
        $tokens = array_map(static fn(array $it) => $it['tokens'], $iterations);
        $walls = array_map(static fn(array $it) => $it['wall'], $iterations);
        $failedRates = array_map(static fn(array $it) => $it['failed'] / $nTasks, $iterations);

        return new PolicyBResult(
            expectedTokens: $this->mean($tokens),
            ciLowTokens: $this->percentile($tokens, 2.5),
            ciHighTokens: $this->percentile($tokens, 97.5),
            expectedWallClockS: $this->mean($walls),
            ciLowWallClockS: $this->percentile($walls, 2.5),
            ciHighWallClockS: $this->percentile($walls, 97.5),
            maxTierFailRate: $this->mean($failedRates),
        );
    }

    /**
     * @param list<int|float> $values
     */
    private function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    /**
     * Nearest-rank percentile: returns the value at position ceil(p/100 * n), 1-indexed.
     *
     * @param list<int|float> $values
     */
    private function percentile(array $values, float $percentile): float
    {
        $sorted = $values;
        sort($sorted);
        $n = count($sorted);
        $rank = (int) max(1, (int) ceil($percentile / 100 * $n));
        return (float) $sorted[$rank - 1];
    }
}
