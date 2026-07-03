<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\StateInitCommand;
use LlmDispatch\Runner\Config;
use LlmDispatch\Runner\State\RunQueue;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StateInitCommandTest extends TestCase
{
    private string $tmpStatePath;
    private Config $config;

    protected function setUp(): void
    {
        $this->tmpStatePath = sys_get_temp_dir() . '/state_' . uniqid() . '.json';
        $this->config = Config::fromFile(dirname(__DIR__, 4) . '/../experiment_config.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpStatePath);
    }

    public function testInitializesWith160RunsAndReturnsZero(): void
    {
        $command = new StateInitCommand(
            new StateManager($this->tmpStatePath),
            new RunQueue($this->config),
            42,
        );

        ob_start();
        $exit = $command->run([]);
        $out = ob_get_clean();

        $this->assertSame(0, $exit);
        $json = json_decode($out, true);
        $this->assertSame(160, $json['runs_queued']);

        $state = json_decode(file_get_contents($this->tmpStatePath), true);
        $this->assertCount(160, $state['remaining_runs']);
    }

    public function testRefusesWhenAlreadyInitializedWithoutForce(): void
    {
        $command = new StateInitCommand(
            new StateManager($this->tmpStatePath),
            new RunQueue($this->config),
            42,
        );

        ob_start(); $command->run([]); ob_end_clean();

        ob_start();
        $exit = $command->run([]);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }

    public function testForceAllowsReinitialization(): void
    {
        $command = new StateInitCommand(
            new StateManager($this->tmpStatePath),
            new RunQueue($this->config),
            42,
        );

        ob_start(); $command->run([]); ob_end_clean();

        ob_start();
        $exit = $command->run(['--force']);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }
}
