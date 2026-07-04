<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\Aggregator;
use LlmDispatch\Runner\Analysis\IncompleteResultsException;
use LlmDispatch\Runner\Config;
use PHPUnit\Framework\TestCase;

final class AggregatorTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = sys_get_temp_dir() . '/results_' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    private function miniConfig(): Config
    {
        return new Config(
            schemaVersion: 1,
            experimentName: 'test',
            planSeed: 42,
            nReplicates: 2,
            maxIterationsPerRun: 3,
            maxWallClockSeconds: 1800,
            tiers: ['haiku', 'sonnet'],
            taskIds: ['task-a', 'task-b'],
            pinnedModels: ['haiku' => null, 'sonnet' => null],
            policy: 'retry-only',
            db: [],
        );
    }

    /**
     * @param array{task_id: string, model_tier: string, n: int, outcome?: string} $overrides
     */
    private function rowLine(array $overrides): string
    {
        $defaults = [
            'run_id' => $overrides['task_id'] . '-' . $overrides['model_tier'] . '-' . $overrides['n'],
            'task_id' => $overrides['task_id'],
            'model_tier' => $overrides['model_tier'],
            'model_id' => 'claude-' . $overrides['model_tier'] . '-stub',
            'n' => $overrides['n'],
            'outcome' => $overrides['outcome'] ?? 'passed',
            'iterations_used' => 1,
            'tokens_subagent_in' => 10_000,
            'tokens_subagent_out' => 2_000,
            'tokens_pm_overhead' => 500,
            'wall_clock_subagent_s' => 150,
            'wall_clock_total_s' => 155,
            'timestamp_start' => '2026-04-23T10:00:00Z',
            'timestamp_end' => '2026-04-23T10:02:35Z',
        ];
        return json_encode($defaults, JSON_THROW_ON_ERROR);
    }

    private function writeComplete(): void
    {
        $lines = [];
        foreach (['task-a', 'task-b'] as $taskId) {
            foreach (['haiku', 'sonnet'] as $tier) {
                foreach ([1, 2] as $n) {
                    $lines[] = $this->rowLine(['task_id' => $taskId, 'model_tier' => $tier, 'n' => $n]);
                }
            }
        }
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");
    }

    public function testAggregatesCompleteMatrix(): void
    {
        $this->writeComplete();

        $matrix = (new Aggregator())->aggregate($this->tmpPath, $this->miniConfig());

        $this->assertArrayHasKey('task-a', $matrix);
        $this->assertArrayHasKey('task-b', $matrix);
        $this->assertArrayHasKey('haiku', $matrix['task-a']);
        $this->assertArrayHasKey('sonnet', $matrix['task-b']);
        $this->assertSame(2, $matrix['task-a']['haiku']->nRuns);
    }

    public function testThrowsOnMissingCell(): void
    {
        $lines = [
            $this->rowLine(['task_id' => 'task-a', 'model_tier' => 'haiku', 'n' => 1]),
            $this->rowLine(['task_id' => 'task-a', 'model_tier' => 'haiku', 'n' => 2]),
            $this->rowLine(['task_id' => 'task-a', 'model_tier' => 'sonnet', 'n' => 1]),
            $this->rowLine(['task_id' => 'task-a', 'model_tier' => 'sonnet', 'n' => 2]),
        ];
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        $this->expectException(IncompleteResultsException::class);
        (new Aggregator())->aggregate($this->tmpPath, $this->miniConfig());
    }

    public function testThrowsOnMissingReplicates(): void
    {
        $lines = [];
        foreach (['task-a', 'task-b'] as $taskId) {
            foreach (['haiku', 'sonnet'] as $tier) {
                $lines[] = $this->rowLine(['task_id' => $taskId, 'model_tier' => $tier, 'n' => 1]);
            }
        }
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        $this->expectException(IncompleteResultsException::class);
        (new Aggregator())->aggregate($this->tmpPath, $this->miniConfig());
    }

    public function testIgnoresBlankLines(): void
    {
        $this->writeComplete();
        file_put_contents($this->tmpPath, file_get_contents($this->tmpPath) . "\n\n");

        $matrix = (new Aggregator())->aggregate($this->tmpPath, $this->miniConfig());
        $this->assertSame(2, $matrix['task-a']['haiku']->nRuns);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Aggregator())->aggregate('/nonexistent/path.jsonl', $this->miniConfig());
    }

    public function testKeepsOnlyLastRowPerRunId(): void
    {
        // Build a complete matrix, but make one row have outcome="failed"
        $lines = [];
        foreach (['task-a', 'task-b'] as $taskId) {
            foreach (['haiku', 'sonnet'] as $tier) {
                foreach ([1, 2] as $n) {
                    // Make task-a-haiku-1 have outcome="failed"
                    $outcome = ($taskId === 'task-a' && $tier === 'haiku' && $n === 1) ? 'failed' : 'passed';
                    $lines[] = $this->rowLine(['task_id' => $taskId, 'model_tier' => $tier, 'n' => $n, 'outcome' => $outcome]);
                }
            }
        }

        // Append a duplicate of task-a-haiku-1 with outcome="passed" (this is the retry, which succeeded)
        $lines[] = $this->rowLine(['task_id' => 'task-a', 'model_tier' => 'haiku', 'n' => 1, 'outcome' => 'passed']);

        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        // Aggregation should succeed (because after dedup, we have exactly 2 unique run_ids per cell)
        $matrix = (new Aggregator())->aggregate($this->tmpPath, $this->miniConfig());

        // The task-a-haiku cell should have 2 runs, and both should be "passed"
        // (the last occurrence of n=1 is "passed", and n=2 is always "passed")
        $this->assertSame(2, $matrix['task-a']['haiku']->nPassed);
        $this->assertSame(1.0, $matrix['task-a']['haiku']->passRate);
    }
}
