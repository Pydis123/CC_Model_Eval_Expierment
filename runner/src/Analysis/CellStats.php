<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

use InvalidArgumentException;

final class CellStats
{
    /** @var list<ResultsRow> */
    public readonly array $runs;
    public readonly int $nRuns;
    public readonly int $nPassed;
    public readonly float $passRate;
    public readonly float $meanTokens;
    public readonly float $meanWallClockS;
    public readonly float $stdTokens;
    public readonly float $stdWallClockS;
    public readonly float $meanIterations;

    /**
     * @param list<ResultsRow> $runs
     */
    public function __construct(array $runs)
    {
        if ($runs === []) {
            throw new InvalidArgumentException('CellStats requires at least one run');
        }

        $this->runs = $runs;
        $this->nRuns = count($runs);
        $this->nPassed = count(array_filter($runs, static fn(ResultsRow $r) => $r->outcome === 'passed'));
        $this->passRate = $this->nPassed / $this->nRuns;

        $tokens = array_map(static fn(ResultsRow $r) => $r->tokensSubagentTotal(), $runs);
        $walls = array_map(static fn(ResultsRow $r) => $r->wallClockSubagentS, $runs);
        $iters = array_map(static fn(ResultsRow $r) => $r->iterationsUsed, $runs);

        $this->meanTokens = array_sum($tokens) / $this->nRuns;
        $this->meanWallClockS = array_sum($walls) / $this->nRuns;
        $this->meanIterations = array_sum($iters) / $this->nRuns;
        $this->stdTokens = $this->populationStdDev($tokens, $this->meanTokens);
        $this->stdWallClockS = $this->populationStdDev($walls, $this->meanWallClockS);
    }

    /**
     * @param list<int|float> $values
     */
    private function populationStdDev(array $values, float $mean): float
    {
        $sumSquares = 0.0;
        foreach ($values as $v) {
            $sumSquares += ($v - $mean) ** 2;
        }
        return sqrt($sumSquares / count($values));
    }
}
