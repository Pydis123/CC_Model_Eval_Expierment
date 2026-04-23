<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\State\RunQueue;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;

final class StateInitCommand implements CommandInterface
{
    public function __construct(
        private readonly StateManager $stateManager,
        private readonly RunQueue $runQueue,
        private readonly int $seed,
    ) {}

    public function run(array $args): int
    {
        $force = in_array('--force', $args, true);

        $state = $this->stateManager->load();
        if ($state->remainingRuns !== [] || $state->completedRuns !== []) {
            if (!$force) {
                fwrite(STDERR, "State already initialized. Use --force to reinitialize.\n");
                return 2;
            }
        }

        $runs = $this->runQueue->plan($this->seed);
        $newState = State::empty()->withRemainingRuns($runs);
        $this->stateManager->save($newState);

        echo json_encode(['runs_queued' => count($runs)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        return 0;
    }
}
