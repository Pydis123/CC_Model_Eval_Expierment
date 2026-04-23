<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\PinCheckPingCommand;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class PinCheckPingCommandTest extends TestCase
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

    public function testStoresTierModelInPinnedModels(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new PinCheckPingCommand($manager);

        ob_start();
        $exit = $command->run(['--tier=opus', '--model-id=claude-opus-4-7']);
        $out = ob_get_clean();

        $this->assertSame(0, $exit);
        $json = json_decode($out, true);
        $this->assertSame('opus', $json['tier']);
        $this->assertSame('claude-opus-4-7', $json['model_id']);
        $this->assertTrue($json['stored']);

        $state = $manager->load();
        $this->assertSame('claude-opus-4-7', $state->pinnedModels['opus']);
    }

    public function testPreservesOtherTiers(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withPinnedModels([
            'haiku' => 'existing-haiku',
            'sonnet' => 'existing-sonnet',
            'opus' => 'existing-opus',
        ]));

        $command = new PinCheckPingCommand($manager);

        ob_start();
        $command->run(['--tier=opus', '--model-id=new-opus']);
        ob_end_clean();

        $state = $manager->load();
        $this->assertSame('existing-haiku', $state->pinnedModels['haiku']);
        $this->assertSame('new-opus', $state->pinnedModels['opus']);
    }

    public function testRejectsMissingArgs(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new PinCheckPingCommand($manager);

        ob_start();
        $exit = $command->run(['--tier=opus']);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }
}
