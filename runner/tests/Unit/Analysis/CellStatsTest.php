<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use InvalidArgumentException;
use LlmDispatch\Runner\Analysis\CellStats;
use LlmDispatch\Runner\Analysis\ResultsRow;
use PHPUnit\Framework\TestCase;

final class CellStatsTest extends TestCase
{
    /**
     * @param array{outcome?: string, tokens_in?: int, tokens_out?: int, wall_clock_s?: int, iterations?: int} $overrides
     */
    private function makeRow(array $overrides = []): ResultsRow
    {
        return ResultsRow::fromArray([
            'run_id' => 'x',
            'task_id' => '001-i18n-status-flik',
            'model_tier' => 'haiku',
            'model_id' => 'claude-haiku-4-5-20251001',
            'n' => 1,
            'outcome' => $overrides['outcome'] ?? 'passed',
            'iterations_used' => $overrides['iterations'] ?? 1,
            'tokens_subagent_in' => $overrides['tokens_in'] ?? 10_000,
            'tokens_subagent_out' => $overrides['tokens_out'] ?? 2_500,
            'tokens_pm_overhead' => 800,
            'wall_clock_subagent_s' => $overrides['wall_clock_s'] ?? 180,
            'wall_clock_total_s' => ($overrides['wall_clock_s'] ?? 180) + 5,
            'timestamp_start' => '2026-04-23T10:00:00Z',
            'timestamp_end' => '2026-04-23T10:03:05Z',
        ]);
    }

    public function testComputesPassRateMeanAndStdDev(): void
    {
        $runs = [
            $this->makeRow(['outcome' => 'passed', 'tokens_in' => 8_000, 'tokens_out' => 2_000, 'wall_clock_s' => 100]),
            $this->makeRow(['outcome' => 'passed', 'tokens_in' => 10_000, 'tokens_out' => 2_000, 'wall_clock_s' => 200]),
            $this->makeRow(['outcome' => 'failed', 'tokens_in' => 12_000, 'tokens_out' => 2_000, 'wall_clock_s' => 300]),
        ];

        $stats = new CellStats($runs);

        $this->assertSame(3, $stats->nRuns);
        $this->assertSame(2, $stats->nPassed);
        $this->assertEqualsWithDelta(2 / 3, $stats->passRate, 0.0001);
        $this->assertEqualsWithDelta(12_000.0, $stats->meanTokens, 0.0001);
        $this->assertEqualsWithDelta(200.0, $stats->meanWallClockS, 0.0001);
    }

    public function testAllFailed(): void
    {
        $runs = [
            $this->makeRow(['outcome' => 'failed']),
            $this->makeRow(['outcome' => 'failed']),
            $this->makeRow(['outcome' => 'failed']),
        ];

        $stats = new CellStats($runs);

        $this->assertSame(0, $stats->nPassed);
        $this->assertSame(0.0, $stats->passRate);
    }

    public function testAllPassed(): void
    {
        $runs = [
            $this->makeRow(['outcome' => 'passed']),
            $this->makeRow(['outcome' => 'passed']),
            $this->makeRow(['outcome' => 'passed']),
        ];

        $stats = new CellStats($runs);

        $this->assertSame(3, $stats->nPassed);
        $this->assertSame(1.0, $stats->passRate);
    }

    public function testRejectsEmptyRuns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CellStats([]);
    }

    public function testExposesRawRunsForBootstrap(): void
    {
        $runs = [$this->makeRow(), $this->makeRow(), $this->makeRow()];
        $stats = new CellStats($runs);

        $this->assertSame($runs, $stats->runs);
    }

    public function testStdDevIsPopulationNotSample(): void
    {
        // Runs with tokens [10000, 12000, 14000] total (in+out). Mean = 12000.
        // Population std = sqrt(((10k-12k)^2 + 0 + (14k-12k)^2) / 3) = sqrt(8_000_000 / 3) ≈ 1632.99
        $runs = [
            $this->makeRow(['tokens_in' => 8_000, 'tokens_out' => 2_000]),
            $this->makeRow(['tokens_in' => 10_000, 'tokens_out' => 2_000]),
            $this->makeRow(['tokens_in' => 12_000, 'tokens_out' => 2_000]),
        ];

        $stats = new CellStats($runs);
        $this->assertEqualsWithDelta(1632.99, $stats->stdTokens, 0.1);
    }
}
