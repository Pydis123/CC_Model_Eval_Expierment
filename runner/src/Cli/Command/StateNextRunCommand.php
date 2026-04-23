<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\State\StateManager;

final class StateNextRunCommand implements CommandInterface
{
    public function __construct(
        private readonly StateManager $manager,
        private readonly string $nowTimestamp,
    ) {}

    public function run(array $args): int
    {
        $peek = in_array('--peek', $args, true);

        $state = $this->manager->load();
        if ($state->remainingRuns === []) {
            fwrite(STDERR, "No remaining runs.\n");
            return 2;
        }

        $next = $state->remainingRuns[0];
        if ($next->claimedAt === null && !$peek) {
            $claimed = $next->withClaimedAt($this->nowTimestamp);
            $this->manager->save($state->replaceRun($claimed));
            $next = $claimed;
        }

        echo json_encode(
            $next->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";

        return 0;
    }
}
