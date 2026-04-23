<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\BootstrapSimulator;
use LlmDispatch\Runner\Analysis\CellStats;
use LlmDispatch\Runner\Analysis\ResultsRow;
use PHPUnit\Framework\TestCase;

final class BootstrapSimulatorTest extends TestCase
{
    /**
     * @param array{tier: string, outcome?: string, tokens?: int, wall?: int} $p
     */
    private function row(array $p): ResultsRow
    {
        return ResultsRow::fromArray([
            'run_id' => 'x',
            'task_id' => 'task-a',
            'model_tier' => $p['tier'],
            'model_id' => 'stub',
            'n' => 1,
            'outcome' => $p['outcome'] ?? 'passed',
            'iterations_used' => 1,
            'tokens_subagent_in' => ($p['tokens'] ?? 10_000) - 2_000,
            'tokens_subagent_out' => 2_000,
            'tokens_pm_overhead' => 0,
            'wall_clock_subagent_s' => $p['wall'] ?? 100,
            'wall_clock_total_s' => ($p['wall'] ?? 100) + 5,
            'timestamp_start' => '2026-04-23T10:00:00Z',
            'timestamp_end' => '2026-04-23T10:01:45Z',
        ]);
    }

    /**
     * @return array<string, array<string, CellStats>>
     */
    private function matrixAllHaikuPasses(): array
    {
        return [
            'task-a' => [
                'haiku' => new CellStats([
                    $this->row(['tier' => 'haiku', 'outcome' => 'passed', 'tokens' => 10_000, 'wall' => 100]),
                    $this->row(['tier' => 'haiku', 'outcome' => 'passed', 'tokens' => 10_000, 'wall' => 100]),
                    $this->row(['tier' => 'haiku', 'outcome' => 'passed', 'tokens' => 10_000, 'wall' => 100]),
                ]),
                'sonnet' => new CellStats([
                    $this->row(['tier' => 'sonnet', 'outcome' => 'passed', 'tokens' => 20_000, 'wall' => 200]),
                    $this->row(['tier' => 'sonnet', 'outcome' => 'passed', 'tokens' => 20_000, 'wall' => 200]),
                    $this->row(['tier' => 'sonnet', 'outcome' => 'passed', 'tokens' => 20_000, 'wall' => 200]),
                ]),
                'opus' => new CellStats([
                    $this->row(['tier' => 'opus', 'outcome' => 'passed', 'tokens' => 40_000, 'wall' => 400]),
                    $this->row(['tier' => 'opus', 'outcome' => 'passed', 'tokens' => 40_000, 'wall' => 400]),
                    $this->row(['tier' => 'opus', 'outcome' => 'passed', 'tokens' => 40_000, 'wall' => 400]),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, array<string, CellStats>>
     */
    private function matrixAllFailed(): array
    {
        return [
            'task-a' => [
                'haiku' => new CellStats([
                    $this->row(['tier' => 'haiku', 'outcome' => 'failed', 'tokens' => 10_000, 'wall' => 100]),
                    $this->row(['tier' => 'haiku', 'outcome' => 'failed', 'tokens' => 10_000, 'wall' => 100]),
                    $this->row(['tier' => 'haiku', 'outcome' => 'failed', 'tokens' => 10_000, 'wall' => 100]),
                ]),
                'sonnet' => new CellStats([
                    $this->row(['tier' => 'sonnet', 'outcome' => 'failed', 'tokens' => 20_000, 'wall' => 200]),
                    $this->row(['tier' => 'sonnet', 'outcome' => 'failed', 'tokens' => 20_000, 'wall' => 200]),
                    $this->row(['tier' => 'sonnet', 'outcome' => 'failed', 'tokens' => 20_000, 'wall' => 200]),
                ]),
                'opus' => new CellStats([
                    $this->row(['tier' => 'opus', 'outcome' => 'failed', 'tokens' => 40_000, 'wall' => 400]),
                    $this->row(['tier' => 'opus', 'outcome' => 'failed', 'tokens' => 40_000, 'wall' => 400]),
                    $this->row(['tier' => 'opus', 'outcome' => 'failed', 'tokens' => 40_000, 'wall' => 400]),
                ]),
            ],
        ];
    }

    public function testAllHaikuPassesProducesHaikuOnlyCost(): void
    {
        $sim = (new BootstrapSimulator())->simulate(
            matrix: $this->matrixAllHaikuPasses(),
            taskIds: ['task-a'],
            tiers: ['haiku', 'sonnet', 'opus'],
            samples: 1000,
            seed: 42,
        );

        $this->assertEqualsWithDelta(10_000.0, $sim->overall->expectedTokens, 0.001);
        $this->assertEqualsWithDelta(100.0, $sim->overall->expectedWallClockS, 0.001);
        $this->assertSame(0.0, $sim->overall->maxTierFailRate);
    }

    public function testAllFailedProducesMaxTierFail(): void
    {
        $sim = (new BootstrapSimulator())->simulate(
            matrix: $this->matrixAllFailed(),
            taskIds: ['task-a'],
            tiers: ['haiku', 'sonnet', 'opus'],
            samples: 1000,
            seed: 42,
        );

        $this->assertSame(1.0, $sim->overall->maxTierFailRate);
        $this->assertEqualsWithDelta(70_000.0, $sim->overall->expectedTokens, 0.001);
        $this->assertEqualsWithDelta(700.0, $sim->overall->expectedWallClockS, 0.001);
    }

    public function testSeededOutputIsDeterministic(): void
    {
        $simulator = new BootstrapSimulator();
        $matrix = $this->matrixAllHaikuPasses();

        $a = $simulator->simulate($matrix, ['task-a'], ['haiku', 'sonnet', 'opus'], 100, 42);
        $b = $simulator->simulate($matrix, ['task-a'], ['haiku', 'sonnet', 'opus'], 100, 42);

        $this->assertSame($a->overall->expectedTokens, $b->overall->expectedTokens);
        $this->assertSame($a->overall->ciLowTokens, $b->overall->ciLowTokens);
        $this->assertSame($a->overall->ciHighTokens, $b->overall->ciHighTokens);
    }

    public function testDifferentSeedsProduceDifferentResults(): void
    {
        $mixed = [
            'task-a' => [
                'haiku' => new CellStats([
                    $this->row(['tier' => 'haiku', 'outcome' => 'passed', 'tokens' => 10_000, 'wall' => 100]),
                    $this->row(['tier' => 'haiku', 'outcome' => 'failed', 'tokens' => 30_000, 'wall' => 300]),
                    $this->row(['tier' => 'haiku', 'outcome' => 'passed', 'tokens' => 20_000, 'wall' => 200]),
                ]),
                'sonnet' => new CellStats([
                    $this->row(['tier' => 'sonnet', 'outcome' => 'passed', 'tokens' => 40_000, 'wall' => 400]),
                    $this->row(['tier' => 'sonnet', 'outcome' => 'passed', 'tokens' => 40_000, 'wall' => 400]),
                    $this->row(['tier' => 'sonnet', 'outcome' => 'passed', 'tokens' => 40_000, 'wall' => 400]),
                ]),
                'opus' => new CellStats([
                    $this->row(['tier' => 'opus', 'outcome' => 'passed', 'tokens' => 80_000, 'wall' => 800]),
                    $this->row(['tier' => 'opus', 'outcome' => 'passed', 'tokens' => 80_000, 'wall' => 800]),
                    $this->row(['tier' => 'opus', 'outcome' => 'passed', 'tokens' => 80_000, 'wall' => 800]),
                ]),
            ],
        ];

        $simulator = new BootstrapSimulator();
        $a = $simulator->simulate($mixed, ['task-a'], ['haiku', 'sonnet', 'opus'], 100, 42);
        $b = $simulator->simulate($mixed, ['task-a'], ['haiku', 'sonnet', 'opus'], 100, 7);

        $this->assertNotSame($a->overall->expectedTokens, $b->overall->expectedTokens);
    }

    public function testStoresSamplesAndSeed(): void
    {
        $sim = (new BootstrapSimulator())->simulate(
            $this->matrixAllHaikuPasses(),
            ['task-a'],
            ['haiku', 'sonnet', 'opus'],
            500,
            123,
        );

        $this->assertSame(500, $sim->bootstrapSamples);
        $this->assertSame(123, $sim->bootstrapSeed);
    }
}
