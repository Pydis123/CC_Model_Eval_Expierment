<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\State;

use LlmDispatch\Runner\State\Run;
use PHPUnit\Framework\TestCase;

final class RunTest extends TestCase
{
    public function testConstructsWithCoreFields(): void
    {
        $run = new Run(runId: 'abc123', taskId: '003-n-plus-one-fix', modelTier: 'sonnet', n: 2);

        $this->assertSame('abc123', $run->runId);
        $this->assertSame('003-n-plus-one-fix', $run->taskId);
        $this->assertSame('sonnet', $run->modelTier);
        $this->assertSame(2, $run->n);
        $this->assertNull($run->claimedAt);
    }

    public function testWithClaimedAtReturnsNewInstance(): void
    {
        $run = new Run('a', 't', 'opus', 1);
        $claimed = $run->withClaimedAt('2026-04-23T10:00:00Z');

        $this->assertNull($run->claimedAt);
        $this->assertSame('2026-04-23T10:00:00Z', $claimed->claimedAt);
    }

    public function testToArrayRoundtrip(): void
    {
        $run = new Run('abc', '001-x', 'haiku', 3, '2026-04-23T09:00:00Z');

        $this->assertSame([
            'run_id' => 'abc',
            'task_id' => '001-x',
            'model_tier' => 'haiku',
            'n' => 3,
            'claimed_at' => '2026-04-23T09:00:00Z',
        ], $run->toArray());
    }

    public function testFromArrayReconstructs(): void
    {
        $run = Run::fromArray([
            'run_id' => 'abc',
            'task_id' => '001-x',
            'model_tier' => 'haiku',
            'n' => 3,
            'claimed_at' => null,
        ]);

        $this->assertSame('abc', $run->runId);
        $this->assertNull($run->claimedAt);
    }
}
