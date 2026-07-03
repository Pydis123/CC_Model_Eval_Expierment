<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Report;

use LlmDispatch\Runner\Analysis\CellStats;
use LlmDispatch\Runner\Analysis\PolicyBResult;
use LlmDispatch\Runner\Analysis\PolicyBSimulation;
use LlmDispatch\Runner\Analysis\ResultsRow;
use LlmDispatch\Runner\Config;
use LlmDispatch\Runner\Report\FindingsWriter;
use PHPUnit\Framework\TestCase;

final class FindingsWriterTest extends TestCase
{
    private function miniConfig(): Config
    {
        return new Config(
            schemaVersion: 1,
            experimentName: 'test',
            planSeed: 42,
            nReplicates: 3,
            maxIterationsPerRun: 3,
            maxWallClockSeconds: 1800,
            tiers: ['haiku', 'sonnet', 'opus'],
            taskIds: ['task-a'],
            pinnedModels: ['haiku' => null, 'sonnet' => null, 'opus' => null],
            policy: 'retry-only',
            db: [],
        );
    }

    /**
     * @param array{tier: string, outcome?: string} $p
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
            'tokens_subagent_in' => 8_000,
            'tokens_subagent_out' => 2_000,
            'tokens_pm_overhead' => 500,
            'wall_clock_subagent_s' => 100,
            'wall_clock_total_s' => 105,
            'timestamp_start' => '2026-04-23T10:00:00Z',
            'timestamp_end' => '2026-04-23T10:01:45Z',
        ]);
    }

    private function simulation(): PolicyBSimulation
    {
        $policyB = new PolicyBResult(
            expectedTokens: 10_000.0,
            ciLowTokens: 9_000.0,
            ciHighTokens: 11_000.0,
            expectedWallClockS: 100.0,
            ciLowWallClockS: 90.0,
            ciHighWallClockS: 110.0,
            maxTierFailRate: 0.0,
        );
        return new PolicyBSimulation(
            perTask: ['task-a' => $policyB],
            overall: $policyB,
            bootstrapSamples: 1000,
            bootstrapSeed: 42,
        );
    }

    private function fourTierConfig(): Config
    {
        return new Config(
            schemaVersion: 1,
            experimentName: 'test',
            planSeed: 42,
            nReplicates: 3,
            maxIterationsPerRun: 3,
            maxWallClockSeconds: 1800,
            tiers: ['haiku', 'sonnet', 'opus', 'fable'],
            taskIds: ['task-a'],
            pinnedModels: ['haiku' => null, 'sonnet' => null, 'opus' => null, 'fable' => null],
            policy: 'retry-only',
            db: [],
        );
    }

    private function renderWithFourTiers(): string
    {
        $config = $this->fourTierConfig();

        $haikuRuns = [$this->row(['tier' => 'haiku']), $this->row(['tier' => 'haiku']), $this->row(['tier' => 'haiku'])];
        $sonnetRuns = [$this->row(['tier' => 'sonnet']), $this->row(['tier' => 'sonnet']), $this->row(['tier' => 'sonnet'])];
        $opusRuns = [$this->row(['tier' => 'opus']), $this->row(['tier' => 'opus']), $this->row(['tier' => 'opus'])];
        $fableRuns = [$this->row(['tier' => 'fable']), $this->row(['tier' => 'fable']), $this->row(['tier' => 'fable'])];

        $matrix = [
            'task-a' => [
                'haiku' => new CellStats($haikuRuns),
                'sonnet' => new CellStats($sonnetRuns),
                'opus' => new CellStats($opusRuns),
                'fable' => new CellStats($fableRuns),
            ],
        ];

        return (new FindingsWriter())->render(
            matrix: $matrix,
            simulation: $this->simulation(),
            config: $config,
            sourcePath: 'results/results.jsonl',
            rowCount: 12,
            generatedAt: '2026-07-03T00:00:00Z',
        );
    }

    /**
     * @return array<string, array<string, CellStats>>
     */
    private function matrix(): array
    {
        $haikuRuns = [$this->row(['tier' => 'haiku']), $this->row(['tier' => 'haiku']), $this->row(['tier' => 'haiku'])];
        $sonnetRuns = [$this->row(['tier' => 'sonnet']), $this->row(['tier' => 'sonnet']), $this->row(['tier' => 'sonnet'])];
        $opusRuns = [$this->row(['tier' => 'opus']), $this->row(['tier' => 'opus']), $this->row(['tier' => 'opus'])];

        return [
            'task-a' => [
                'haiku' => new CellStats($haikuRuns),
                'sonnet' => new CellStats($sonnetRuns),
                'opus' => new CellStats($opusRuns),
            ],
        ];
    }

    public function testRendersSectionsInOrder(): void
    {
        $md = (new FindingsWriter())->render(
            matrix: $this->matrix(),
            simulation: $this->simulation(),
            config: $this->miniConfig(),
            sourcePath: 'results/results.jsonl',
            rowCount: 9,
            generatedAt: '2026-04-23T12:00:00Z',
        );

        $this->assertStringContainsString('# LLM Dispatch Experiment — Findings', $md);
        $this->assertStringContainsString('## Summary', $md);
        $this->assertStringContainsString('## Per-task results (Policy A — retry-only)', $md);
        $this->assertStringContainsString('## Policy B simulation (cheapest-first escalation)', $md);
        $this->assertStringContainsString('## Reproducibility', $md);

        $summaryPos = strpos($md, '## Summary');
        $perTaskPos = strpos($md, '## Per-task results');
        $policyBPos = strpos($md, '## Policy B simulation');
        $reproPos = strpos($md, '## Reproducibility');
        $this->assertNotFalse($summaryPos);
        $this->assertLessThan($perTaskPos, $summaryPos);
        $this->assertLessThan($policyBPos, $perTaskPos);
        $this->assertLessThan($reproPos, $policyBPos);
    }

    public function testIncludesTaskIdInPerTaskSection(): void
    {
        $md = (new FindingsWriter())->render(
            matrix: $this->matrix(),
            simulation: $this->simulation(),
            config: $this->miniConfig(),
            sourcePath: 'results/results.jsonl',
            rowCount: 9,
            generatedAt: '2026-04-23T12:00:00Z',
        );

        $this->assertStringContainsString('### task-a', $md);
    }

    public function testIncludesBootstrapSeedInHeader(): void
    {
        $md = (new FindingsWriter())->render(
            matrix: $this->matrix(),
            simulation: $this->simulation(),
            config: $this->miniConfig(),
            sourcePath: 'results/results.jsonl',
            rowCount: 9,
            generatedAt: '2026-04-23T12:00:00Z',
        );

        $this->assertStringContainsString('1000 samples', $md);
        $this->assertStringContainsString('seed=42', $md);
    }

    public function testFormatsNumbersWithThousandSeparator(): void
    {
        $md = (new FindingsWriter())->render(
            matrix: $this->matrix(),
            simulation: $this->simulation(),
            config: $this->miniConfig(),
            sourcePath: 'results/results.jsonl',
            rowCount: 9,
            generatedAt: '2026-04-23T12:00:00Z',
        );

        $this->assertStringContainsString('10,000', $md);
    }

    public function testReproducibilityBlockMentionsDiffWorkflow(): void
    {
        $md = (new FindingsWriter())->render(
            matrix: $this->matrix(),
            simulation: $this->simulation(),
            config: $this->miniConfig(),
            sourcePath: 'results/results.jsonl',
            rowCount: 9,
            generatedAt: '2026-04-23T12:00:00Z',
        );

        $this->assertStringContainsString('runner/bin/cli report', $md);
        $this->assertStringContainsString('diff', $md);
    }

    public function testSummaryNamesAllConfiguredTiers(): void
    {
        $md = $this->renderWithFourTiers();
        $this->assertStringContainsString('4 model tiers (haiku, sonnet, opus, fable)', $md);
    }
}
