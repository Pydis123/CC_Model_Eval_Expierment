<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\DispositionTally;
use PHPUnit\Framework\TestCase;

final class DispositionTallyTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = sys_get_temp_dir() . '/disposition_tally_' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    public function testTalliesAllRowsIncludingSuperseded(): void
    {
        // Write three lines: two with same run_id (one model_rerouted, one completed),
        // one other task with refused_in_band
        $lines = [
            json_encode([
                'run_id' => 'run-1',
                'task_id' => 't1',
                'model_tier' => 'fable',
                'dispatch_disposition' => 'model_rerouted',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'run_id' => 'run-1',
                'task_id' => 't1',
                'model_tier' => 'fable',
                'dispatch_disposition' => 'completed',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'run_id' => 'run-2',
                'task_id' => 't2',
                'model_tier' => 'haiku',
                'dispatch_disposition' => 'refused_in_band',
            ], JSON_THROW_ON_ERROR),
        ];
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        $tally = (new DispositionTally())->tally($this->tmpPath);

        // Assert all three rows counted (both t1/fable rows despite same run_id)
        $this->assertArrayHasKey('t1', $tally);
        $this->assertArrayHasKey('fable', $tally['t1']);
        $this->assertSame(1, $tally['t1']['fable']['model_rerouted'] ?? 0);
        $this->assertSame(1, $tally['t1']['fable']['completed'] ?? 0);

        $this->assertArrayHasKey('t2', $tally);
        $this->assertArrayHasKey('haiku', $tally['t2']);
        $this->assertSame(1, $tally['t2']['haiku']['refused_in_band'] ?? 0);
    }

    public function testMissingDispatchDispositionCountsAsCompleted(): void
    {
        $lines = [
            json_encode([
                'run_id' => 'run-1',
                'task_id' => 't1',
                'model_tier' => 'haiku',
                // dispatch_disposition omitted
            ], JSON_THROW_ON_ERROR),
        ];
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        $tally = (new DispositionTally())->tally($this->tmpPath);

        $this->assertSame(1, $tally['t1']['haiku']['completed'] ?? 0);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        (new DispositionTally())->tally('/nonexistent/path/results.jsonl');
    }

    public function testSkipsEmptyLines(): void
    {
        $lines = [
            json_encode([
                'run_id' => 'run-1',
                'task_id' => 't1',
                'model_tier' => 'haiku',
                'dispatch_disposition' => 'completed',
            ], JSON_THROW_ON_ERROR),
            '',
            json_encode([
                'run_id' => 'run-2',
                'task_id' => 't2',
                'model_tier' => 'fable',
                'dispatch_disposition' => 'completed',
            ], JSON_THROW_ON_ERROR),
        ];
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        $tally = (new DispositionTally())->tally($this->tmpPath);

        $this->assertCount(2, array_keys($tally));
        $this->assertSame(1, $tally['t1']['haiku']['completed'] ?? 0);
        $this->assertSame(1, $tally['t2']['fable']['completed'] ?? 0);
    }

    public function testCastsTaskIdAndTierToString(): void
    {
        $lines = [
            json_encode([
                'run_id' => 'run-1',
                'task_id' => null,  // will be cast to empty string
                'model_tier' => null,  // will be cast to empty string
                'dispatch_disposition' => 'completed',
            ], JSON_THROW_ON_ERROR),
        ];
        file_put_contents($this->tmpPath, implode("\n", $lines) . "\n");

        $tally = (new DispositionTally())->tally($this->tmpPath);

        $this->assertArrayHasKey('', $tally);
        $this->assertArrayHasKey('', $tally['']);
        $this->assertSame(1, $tally['']['']['completed'] ?? 0);
    }
}
