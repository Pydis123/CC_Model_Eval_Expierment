<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use InvalidArgumentException;
use LlmDispatch\Runner\Analysis\ResultsRow;
use PHPUnit\Framework\TestCase;

final class ResultsRowTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRowData(array $overrides = []): array
    {
        return array_merge([
            'run_id' => 'run-001-haiku-1',
            'task_id' => '001-i18n-status-flik',
            'model_tier' => 'haiku',
            'model_id' => 'claude-haiku-4-5-20251001',
            'n' => 1,
            'outcome' => 'passed',
            'iterations_used' => 1,
            'tokens_subagent_in' => 10_000,
            'tokens_subagent_out' => 2_500,
            'tokens_pm_overhead' => 800,
            'wall_clock_subagent_s' => 180,
            'wall_clock_total_s' => 185,
            'timestamp_start' => '2026-04-23T10:00:00Z',
            'timestamp_end' => '2026-04-23T10:03:05Z',
            'evaluator_details' => ['outcome' => 'passed', 'checks' => []],
        ], $overrides);
    }

    public function testParsesValidRow(): void
    {
        $row = ResultsRow::fromArray($this->validRowData());

        $this->assertSame('run-001-haiku-1', $row->runId);
        $this->assertSame('001-i18n-status-flik', $row->taskId);
        $this->assertSame('haiku', $row->modelTier);
        $this->assertSame('passed', $row->outcome);
        $this->assertSame(12_500, $row->tokensSubagentTotal());
        $this->assertSame(180, $row->wallClockSubagentS);
    }

    public function testRejectsInvalidTier(): void
    {
        $data = $this->validRowData();
        $data['model_tier'] = 'gpt-5';

        $this->expectException(InvalidArgumentException::class);
        ResultsRow::fromArray($data);
    }

    public function testRejectsInvalidOutcome(): void
    {
        $data = $this->validRowData();
        $data['outcome'] = 'maybe';

        $this->expectException(InvalidArgumentException::class);
        ResultsRow::fromArray($data);
    }

    public function testRejectsMissingRequiredField(): void
    {
        $data = $this->validRowData();
        unset($data['wall_clock_subagent_s']);

        $this->expectException(InvalidArgumentException::class);
        ResultsRow::fromArray($data);
    }

    public function testTokensSubagentTotalSumsInAndOut(): void
    {
        $row = ResultsRow::fromArray($this->validRowData());
        $this->assertSame(10_000 + 2_500, $row->tokensSubagentTotal());
    }

    public function testAcceptsFableTier(): void
    {
        $row = ResultsRow::fromArray($this->validRowData(['model_tier' => 'fable', 'model_id' => 'claude-fable-5']));
        $this->assertSame('fable', $row->modelTier);
    }
}
