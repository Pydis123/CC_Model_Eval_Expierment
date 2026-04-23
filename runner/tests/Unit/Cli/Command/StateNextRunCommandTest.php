<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\StateNextRunCommand;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StateNextRunCommandTest extends TestCase
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

    public function testReturnsFirstRemainingRunAndClaimsIt(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withRemainingRuns([
            new Run('a', 't1', 'haiku', 1),
            new Run('b', 't1', 'sonnet', 1),
        ]));

        $command = new StateNextRunCommand($manager, '2026-04-23T10:00:00Z');

        ob_start();
        $exit = $command->run([]);
        $out = ob_get_clean();

        $this->assertSame(0, $exit);
        $json = json_decode($out, true);
        $this->assertSame('a', $json['run_id']);

        $reloaded = $manager->load();
        $this->assertSame('2026-04-23T10:00:00Z', $reloaded->remainingRuns[0]->claimedAt);
    }

    public function testPeekDoesNotClaim(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withRemainingRuns([
            new Run('a', 't1', 'haiku', 1),
        ]));

        $command = new StateNextRunCommand($manager, '2026-04-23T10:00:00Z');

        ob_start(); $command->run(['--peek']); ob_end_clean();

        $reloaded = $manager->load();
        $this->assertNull($reloaded->remainingRuns[0]->claimedAt);
    }

    public function testReturnsExistingClaimInsteadOfAdvancing(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withRemainingRuns([
            (new Run('a', 't1', 'haiku', 1))->withClaimedAt('2026-04-23T09:00:00Z'),
            new Run('b', 't1', 'sonnet', 1),
        ]));

        $command = new StateNextRunCommand($manager, '2026-04-23T10:00:00Z');

        ob_start();
        $command->run([]);
        $out = ob_get_clean();

        $json = json_decode($out, true);
        $this->assertSame('a', $json['run_id']);
    }

    public function testExitsTwoWhenQueueEmpty(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new StateNextRunCommand($manager, '2026-04-23T10:00:00Z');

        ob_start();
        $exit = $command->run([]);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }
}
