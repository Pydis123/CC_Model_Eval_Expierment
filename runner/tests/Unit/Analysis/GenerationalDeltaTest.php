<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\GenerationalDelta;
use PHPUnit\Framework\TestCase;

final class GenerationalDeltaTest extends TestCase
{
    public function testComputesTokenDeltaAndPassRatesPerTask(): void
    {
        $old = [
            $this->row('001-x', 'sonnet', 'passed', 100),
            $this->row('001-x', 'sonnet', 'failed', 200),
        ];
        $new = [
            $this->row('001-x', 'sonnet', 'passed', 50),
            $this->row('001-x', 'sonnet', 'passed', 150),
        ];

        $delta = GenerationalDelta::compute($old, $new, 'sonnet');

        $this->assertEqualsWithDelta(150.0, $delta['001-x']['old_tokens'], 0.01);
        $this->assertEqualsWithDelta(100.0, $delta['001-x']['new_tokens'], 0.01);
        $this->assertEqualsWithDelta(-33.33, $delta['001-x']['delta_pct'], 0.1);
        $this->assertEqualsWithDelta(0.5, $delta['001-x']['old_pass'], 0.01);
        $this->assertEqualsWithDelta(1.0, $delta['001-x']['new_pass'], 0.01);
    }

    /** @return array<string, mixed> */
    private function row(string $taskId, string $tier, string $outcome, int $tokens): array
    {
        return [
            'task_id' => $taskId, 'model_tier' => $tier, 'outcome' => $outcome,
            'tokens_subagent_in' => $tokens, 'tokens_subagent_out' => 0,
        ];
    }
}
