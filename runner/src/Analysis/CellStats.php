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
    public readonly ?float $meanRecall;
    public readonly ?float $meanPrecisionAdjusted;
    public readonly ?float $meanRubricTotal;

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

        $this->meanRecall = $this->meanOf($this->metricValues('recall'));
        $this->meanPrecisionAdjusted = $this->meanOf($this->metricValues('precision_adjusted'));
        $this->meanRubricTotal = $this->meanOf($this->metricValues('rubric_total'));
    }

    /**
     * @return ?list<float>
     */
    public function metricValues(string $key): ?array
    {
        $values = [];
        foreach ($this->runs as $run) {
            if ($run->metrics === null || !array_key_exists($key, $run->metrics)) {
                return null;
            }
            $value = $run->metrics[$key];
            if (!is_int($value) && !is_float($value)) {
                return null;
            }
            $values[] = (float) $value;
        }
        return $values;
    }

    /**
     * @param ?list<float> $values
     */
    private function meanOf(?array $values): ?float
    {
        if ($values === null) {
            return null;
        }
        return array_sum($values) / count($values);
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
