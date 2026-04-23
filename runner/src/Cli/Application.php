<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli;

final class Application
{
    public const EXIT_OK = 0;
    public const EXIT_USAGE = 2;

    /**
     * @param array<string, CommandInterface|array<string, CommandInterface>> $registry
     *   'command' => CommandInterface for flat commands,
     *   'command' => ['sub' => CommandInterface] for subcommands.
     */
    public function __construct(private readonly array $registry) {}

    /**
     * @param list<string> $argv  Full argv as PHP gets it (argv[0] is script name).
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;
        if ($command === null || !isset($this->registry[$command])) {
            $this->writeUsage();
            return self::EXIT_USAGE;
        }

        $entry = $this->registry[$command];

        if ($entry instanceof CommandInterface) {
            return $entry->run(array_values(array_slice($argv, 2)));
        }

        // subcommand required
        $subcommand = $argv[2] ?? null;
        if ($subcommand === null || !isset($entry[$subcommand])) {
            $this->writeUsage();
            return self::EXIT_USAGE;
        }

        return $entry[$subcommand]->run(array_values(array_slice($argv, 3)));
    }

    private function writeUsage(): void
    {
        fwrite(STDERR, "Usage: cli <command> [<subcommand>] [args...]\n");
        fwrite(STDERR, "Available commands:\n");
        foreach ($this->registry as $name => $entry) {
            if ($entry instanceof CommandInterface) {
                fwrite(STDERR, "  {$name}\n");
                continue;
            }
            foreach (array_keys($entry) as $sub) {
                fwrite(STDERR, "  {$name} {$sub}\n");
            }
        }
    }
}
