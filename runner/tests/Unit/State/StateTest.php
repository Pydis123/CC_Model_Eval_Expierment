<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\State;

use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use PHPUnit\Framework\TestCase;

final class StateTest extends TestCase
{
    public function testEmptyStateHasSchemaVersionOne(): void
    {
        $state = State::empty();

        $this->assertSame(1, $state->schemaVersion);
        $this->assertSame([], $state->remainingRuns);
        $this->assertSame([], $state->completedRuns);
        $this->assertNull($state->pinnedModels);
    }

    public function testWithRemainingRunsReplacesList(): void
    {
        $state = State::empty();
        $runs = [new Run('a', 't', 'haiku', 1)];

        $new = $state->withRemainingRuns($runs);

        $this->assertCount(1, $new->remainingRuns);
        $this->assertSame('a', $new->remainingRuns[0]->runId);
    }

    public function testMoveToCompletedRemovesFromRemaining(): void
    {
        $state = State::empty()->withRemainingRuns([
            new Run('a', 't', 'haiku', 1),
            new Run('b', 't', 'sonnet', 1),
        ]);

        $new = $state->moveToCompleted('a');

        $this->assertCount(1, $new->remainingRuns);
        $this->assertSame('b', $new->remainingRuns[0]->runId);
        $this->assertCount(1, $new->completedRuns);
        $this->assertSame('a', $new->completedRuns[0]->runId);
    }

    public function testWithPinnedModels(): void
    {
        $state = State::empty();
        $new = $state->withPinnedModels([
            'haiku' => 'claude-haiku-4-5-20251001',
            'sonnet' => 'claude-sonnet-4-6',
            'opus' => 'claude-opus-4-7',
        ]);

        $this->assertSame('claude-opus-4-7', $new->pinnedModels['opus']);
    }

    public function testToArraySerializes(): void
    {
        $state = State::empty()->withRemainingRuns([new Run('a', 't', 'haiku', 1)]);

        $array = $state->toArray();

        $this->assertSame(1, $array['schema_version']);
        $this->assertCount(1, $array['remaining_runs']);
        $this->assertSame('a', $array['remaining_runs'][0]['run_id']);
    }

    public function testFromArrayReconstructs(): void
    {
        $state = State::fromArray([
            'schema_version' => 1,
            'experiment_started_at' => '2026-04-23T10:00:00Z',
            'pinned_models' => ['opus' => 'claude-opus-4-7'],
            'next_run_idx' => 0,
            'completed_runs' => [],
            'remaining_runs' => [
                ['run_id' => 'a', 'task_id' => 't', 'model_tier' => 'haiku', 'n' => 1, 'claimed_at' => null],
            ],
            'current_session_tokens_est' => 0,
            'current_session_started_at' => null,
        ]);

        $this->assertSame('2026-04-23T10:00:00Z', $state->experimentStartedAt);
        $this->assertSame('claude-opus-4-7', $state->pinnedModels['opus']);
        $this->assertCount(1, $state->remainingRuns);
    }

    public function testRequeueCompletedMovesRunToFrontOfRemainingUnclaimed(): void
    {
        $state = State::empty()
            ->withRemainingRuns([
                new Run('b', 't', 'sonnet', 1),
                new Run('c', 't', 'opus', 1),
            ])
            ->moveToCompleted('b');

        // Now we have: remaining=[c], completed=[b]
        $this->assertCount(1, $state->remainingRuns);
        $this->assertCount(1, $state->completedRuns);

        $new = $state->requeueCompleted('b');

        // After requeue: b should be back at front of remaining (with claimedAt=null)
        $this->assertCount(2, $new->remainingRuns);
        $this->assertSame('b', $new->remainingRuns[0]->runId);
        $this->assertNull($new->remainingRuns[0]->claimedAt);
        $this->assertSame('c', $new->remainingRuns[1]->runId);
        $this->assertCount(0, $new->completedRuns);
    }

    public function testRequeueCompletedUnknownRunIdIsNoOp(): void
    {
        $state = State::empty()
            ->withRemainingRuns([
                new Run('a', 't', 'haiku', 1),
            ]);

        $new = $state->requeueCompleted('nonexistent');

        $this->assertCount(1, $new->remainingRuns);
        $this->assertSame('a', $new->remainingRuns[0]->runId);
        $this->assertCount(0, $new->completedRuns);
    }
}
