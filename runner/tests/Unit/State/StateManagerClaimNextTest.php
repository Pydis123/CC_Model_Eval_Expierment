<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\State;

use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StateManagerClaimNextTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = sys_get_temp_dir() . '/state_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    public function testClaimsFirstUnclaimedRunAndSetsClaimedAt(): void
    {
        $initial = State::empty()->withRemainingRuns([
            new Run('run-1', 'task-a', 'haiku', 1),
            new Run('run-2', 'task-a', 'sonnet', 1),
        ]);

        $manager = new StateManager($this->tmpPath);
        $manager->save($initial);

        $claimed = $manager->claimNext('2026-04-23T14:00:00Z');

        $this->assertNotNull($claimed);
        $this->assertSame('run-1', $claimed->runId);
        $this->assertSame('2026-04-23T14:00:00Z', $claimed->claimedAt);

        $reloaded = $manager->load();
        $this->assertSame('2026-04-23T14:00:00Z', $reloaded->remainingRuns[0]->claimedAt);
        $this->assertNull($reloaded->remainingRuns[1]->claimedAt);
    }

    public function testSkipsAlreadyClaimedRuns(): void
    {
        $initial = State::empty()->withRemainingRuns([
            (new Run('run-1', 'task-a', 'haiku', 1))->withClaimedAt('2026-04-23T13:00:00Z'),
            new Run('run-2', 'task-a', 'sonnet', 1),
        ]);

        $manager = new StateManager($this->tmpPath);
        $manager->save($initial);

        $claimed = $manager->claimNext('2026-04-23T14:00:00Z');

        $this->assertNotNull($claimed);
        $this->assertSame('run-2', $claimed->runId);
    }

    public function testReturnsNullWhenAllClaimed(): void
    {
        $initial = State::empty()->withRemainingRuns([
            (new Run('run-1', 'task-a', 'haiku', 1))->withClaimedAt('2026-04-23T13:00:00Z'),
        ]);

        $manager = new StateManager($this->tmpPath);
        $manager->save($initial);

        $claimed = $manager->claimNext('2026-04-23T14:00:00Z');

        $this->assertNull($claimed);
    }

    public function testReturnsNullWhenRemainingEmpty(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $claimed = $manager->claimNext('2026-04-23T14:00:00Z');

        $this->assertNull($claimed);
    }
}
