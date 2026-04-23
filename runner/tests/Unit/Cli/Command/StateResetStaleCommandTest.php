<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\StateResetStaleCommand;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StateResetStaleCommandTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        $this->statePath = sys_get_temp_dir() . '/statereset_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
    }

    public function testClearsStaleClaims(): void
    {
        $state = State::empty()->withRemainingRuns([
            (new Run('run-1', 'task-a', 'haiku', 1))->withClaimedAt('2026-04-23T10:00:00Z'),
            (new Run('run-2', 'task-a', 'sonnet', 1))->withClaimedAt('2026-04-23T13:30:00Z'),
        ]);
        $manager = new StateManager($this->statePath);
        $manager->save($state);

        $cmd = new StateResetStaleCommand(
            stateManager: $manager,
            now: fn() => new \DateTimeImmutable('2026-04-23T14:00:00Z'),
        );

        ob_start();
        $exit = $cmd->run(['--older-than=2h']);
        ob_end_clean();

        $this->assertSame(0, $exit);

        $reloaded = $manager->load();
        $this->assertNull($reloaded->remainingRuns[0]->claimedAt); // 4h old → cleared
        $this->assertSame('2026-04-23T13:30:00Z', $reloaded->remainingRuns[1]->claimedAt); // 30min old → kept
    }

    public function testDryRunDoesNotMutateState(): void
    {
        $state = State::empty()->withRemainingRuns([
            (new Run('run-1', 'task-a', 'haiku', 1))->withClaimedAt('2026-04-23T10:00:00Z'),
        ]);
        $manager = new StateManager($this->statePath);
        $manager->save($state);

        $cmd = new StateResetStaleCommand(
            stateManager: $manager,
            now: fn() => new \DateTimeImmutable('2026-04-23T14:00:00Z'),
        );

        ob_start();
        $exit = $cmd->run(['--older-than=2h', '--dry-run']);
        ob_end_clean();

        $this->assertSame(0, $exit);

        $reloaded = $manager->load();
        $this->assertSame('2026-04-23T10:00:00Z', $reloaded->remainingRuns[0]->claimedAt);
    }

    public function testParsesMultipleDurationFormats(): void
    {
        $cmd = new StateResetStaleCommand(
            stateManager: new StateManager($this->statePath),
            now: fn() => new \DateTimeImmutable('2026-04-23T14:00:00Z'),
        );

        $this->assertSame(1800, $cmd->parseDurationSeconds('30m'));
        $this->assertSame(7200, $cmd->parseDurationSeconds('2h'));
        $this->assertSame(86400, $cmd->parseDurationSeconds('1d'));
    }
}
