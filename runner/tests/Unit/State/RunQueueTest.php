<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\State;

use LlmDispatch\Runner\Config;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\RunQueue;
use PHPUnit\Framework\TestCase;

final class RunQueueTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = Config::fromFile(dirname(__DIR__, 3) . '/../experiment_config.json');
    }

    public function testPlanGenerates160Runs(): void
    {
        $runs = (new RunQueue($this->config))->plan(42);

        $this->assertCount(160, $runs);
    }

    public function testEachTaskHasTwentyRuns(): void
    {
        $runs = (new RunQueue($this->config))->plan(42);

        $perTask = [];
        foreach ($runs as $run) {
            $perTask[$run->taskId] = ($perTask[$run->taskId] ?? 0) + 1;
        }

        foreach ($this->config->taskIds as $taskId) {
            $this->assertSame(20, $perTask[$taskId], "Task {$taskId} should have 20 runs");
        }
    }

    public function testEachTaskHasAllFourTiersAndAllFiveNs(): void
    {
        $runs = (new RunQueue($this->config))->plan(42);

        $perTask = [];
        foreach ($runs as $run) {
            $perTask[$run->taskId][] = $run->modelTier . '-' . $run->n;
        }

        foreach ($perTask as $taskId => $combos) {
            sort($combos);
            $this->assertSame([
                'fable-1', 'fable-2', 'fable-3', 'fable-4', 'fable-5',
                'haiku-1', 'haiku-2', 'haiku-3', 'haiku-4', 'haiku-5',
                'opus-1', 'opus-2', 'opus-3', 'opus-4', 'opus-5',
                'sonnet-1', 'sonnet-2', 'sonnet-3', 'sonnet-4', 'sonnet-5',
            ], $combos, "Task {$taskId} missing combinations");
        }
    }

    public function testOrderIsDeterministicForSameSeed(): void
    {
        $queue = new RunQueue($this->config);
        $runs1 = $queue->plan(42);
        $runs2 = $queue->plan(42);

        $ids1 = array_map(static fn(Run $r) => $r->runId, $runs1);
        $ids2 = array_map(static fn(Run $r) => $r->runId, $runs2);
        $this->assertSame($ids1, $ids2);
    }

    public function testDifferentSeedsGiveDifferentOrders(): void
    {
        $queue = new RunQueue($this->config);
        $runs1 = $queue->plan(42);
        $runs2 = $queue->plan(99);

        $ids1 = array_map(static fn(Run $r) => $r->runId, $runs1);
        $ids2 = array_map(static fn(Run $r) => $r->runId, $runs2);
        $this->assertNotSame($ids1, $ids2);
    }

    public function testTasksAppearInConfigOrderWithShuffledInternalRuns(): void
    {
        $runs = (new RunQueue($this->config))->plan(42);

        $seenTasks = [];
        foreach ($runs as $run) {
            if (!in_array($run->taskId, $seenTasks, true)) {
                $seenTasks[] = $run->taskId;
            }
        }

        $this->assertSame($this->config->taskIds, $seenTasks);
    }

    public function testRunIdIsDeterministicHash(): void
    {
        $runs = (new RunQueue($this->config))->plan(42);
        $second = (new RunQueue($this->config))->plan(42);
        foreach ($runs as $idx => $run) {
            $this->assertSame($run->runId, $second[$idx]->runId);
        }
    }
}
