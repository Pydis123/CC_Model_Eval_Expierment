<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli;

use LlmDispatch\Runner\Cli\Application;
use LlmDispatch\Runner\Cli\CommandInterface;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testRoutesToRegisteredCommand(): void
    {
        $command = new class implements CommandInterface {
            public array $receivedArgs = [];
            public function run(array $args): int
            {
                $this->receivedArgs = $args;
                return 0;
            }
        };

        $app = new Application(['hello' => ['world' => $command]]);

        $exit = $app->run(['cli', 'hello', 'world', '--foo=bar']);

        $this->assertSame(0, $exit);
        $this->assertSame(['--foo=bar'], $command->receivedArgs);
    }

    public function testReturnsUsageExitCodeOnUnknownCommand(): void
    {
        $app = new Application([]);

        $exit = $app->run(['cli', 'bogus']);

        $this->assertSame(2, $exit);
    }

    public function testReturnsUsageExitCodeOnMissingSubcommand(): void
    {
        $command = new class implements CommandInterface {
            public function run(array $args): int { return 0; }
        };
        $app = new Application(['hello' => ['world' => $command]]);

        $exit = $app->run(['cli', 'hello']);

        $this->assertSame(2, $exit);
    }

    public function testCommandWithoutSubcommandIsAccepted(): void
    {
        $command = new class implements CommandInterface {
            public array $receivedArgs = [];
            public function run(array $args): int
            {
                $this->receivedArgs = $args;
                return 0;
            }
        };
        $app = new Application(['evaluate' => $command]);

        $exit = $app->run(['cli', 'evaluate', '--task=003']);

        $this->assertSame(0, $exit);
        $this->assertSame(['--task=003'], $command->receivedArgs);
    }

    public function testReturnsUsageExitCodeWhenArgvEmpty(): void
    {
        $app = new Application([]);

        $exit = $app->run(['cli']);

        $this->assertSame(2, $exit);
    }
}
