<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Probe\QueryCountProbe;

final class ProbeQueryCountCommand implements CommandInterface
{
    public function __construct(private readonly QueryCountProbe $probe) {}

    public function run(array $args): int
    {
        $worktree = null;
        $route = null;
        $authAsAdmin = in_array('--auth-as-admin', $args, true);

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--worktree=')) {
                $worktree = substr($arg, 11);
            } elseif (str_starts_with($arg, '--route=')) {
                $route = substr($arg, 8);
            }
        }

        if ($worktree === null || $route === null) {
            fwrite(STDERR, "Required: --worktree= --route=\n");
            return 2;
        }

        $count = $this->probe->count($worktree, $route, $authAsAdmin);

        echo json_encode([
            'route' => $route,
            'query_count' => $count,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        return 0;
    }
}
