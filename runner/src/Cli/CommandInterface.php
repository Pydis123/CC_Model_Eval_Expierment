<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli;

interface CommandInterface
{
    /**
     * @param list<string> $args  Command arguments, already stripped of the command/subcommand prefix.
     */
    public function run(array $args): int;
}
