<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\StatePinModelsCommand;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StatePinModelsCommandTest extends TestCase
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

    public function testWritesPinnedModelsToState(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new StatePinModelsCommand($manager, ['haiku', 'sonnet', 'opus']);

        ob_start();
        $exit = $command->run([
            '--haiku=claude-haiku-4-5-20251001',
            '--sonnet=claude-sonnet-4-6',
            '--opus=claude-opus-4-7',
        ]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $state = $manager->load();
        $this->assertSame('claude-opus-4-7', $state->pinnedModels['opus']);
        $this->assertSame('claude-sonnet-4-6', $state->pinnedModels['sonnet']);
    }

    public function testRefusesWhenAlreadyPinnedWithoutForce(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withPinnedModels([
            'haiku' => 'claude-haiku-4-5', 'sonnet' => 'claude-sonnet-4-6', 'opus' => 'claude-opus-4-7',
        ]));

        $command = new StatePinModelsCommand($manager, ['haiku', 'sonnet', 'opus']);

        ob_start();
        $exit = $command->run([
            '--haiku=claude-haiku-4-5-20251001',
            '--sonnet=claude-sonnet-4-6',
            '--opus=claude-opus-4-7',
        ]);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }

    public function testRequiresAllThreeTierFlags(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new StatePinModelsCommand($manager, ['haiku', 'sonnet', 'opus']);

        ob_start();
        $exit = $command->run(['--haiku=x', '--sonnet=y']);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }

    public function testPinsAllFourConfiguredTiers(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new StatePinModelsCommand($manager, ['haiku', 'sonnet', 'opus', 'fable']);

        ob_start();
        $exit = $command->run([
            '--haiku=claude-haiku-4-5-20251001',
            '--sonnet=claude-sonnet-5',
            '--opus=claude-opus-4-8',
            '--fable=claude-fable-5',
        ]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertSame('claude-fable-5', $manager->load()->pinnedModels['fable']);
    }
}
