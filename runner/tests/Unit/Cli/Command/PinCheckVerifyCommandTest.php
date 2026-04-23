<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\PinCheckVerifyCommand;
use LlmDispatch\Runner\PinCheck\PinCheckService;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class PinCheckVerifyCommandTest extends TestCase
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

    public function testExitsZeroOnMatch(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withPinnedModels([
            'haiku' => 'claude-haiku-4-5',
            'sonnet' => 'claude-sonnet-4-6',
            'opus' => 'claude-opus-4-7',
        ]));

        $command = new PinCheckVerifyCommand($manager, new PinCheckService());

        ob_start();
        $exit = $command->run(['--tier=opus', '--actual-model-id=claude-opus-4-7']);
        $out = ob_get_clean();

        $this->assertSame(0, $exit);
        $json = json_decode($out, true);
        $this->assertTrue($json['match']);
    }

    public function testExitsOneOnDrift(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty()->withPinnedModels([
            'haiku' => 'h', 'sonnet' => 's', 'opus' => 'claude-opus-4-7',
        ]));

        $command = new PinCheckVerifyCommand($manager, new PinCheckService());

        ob_start();
        $exit = $command->run(['--tier=opus', '--actual-model-id=claude-opus-4-6']);
        ob_end_clean();

        $this->assertSame(1, $exit);
    }

    public function testExitsTwoWhenTierNotPinned(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $command = new PinCheckVerifyCommand($manager, new PinCheckService());

        ob_start();
        $exit = $command->run(['--tier=opus', '--actual-model-id=claude-opus-4-7']);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }
}
