<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\PinCheck\PinCheckService;
use LlmDispatch\Runner\State\StateManager;

final class PinCheckVerifyCommand implements CommandInterface
{
    public function __construct(
        private readonly StateManager $manager,
        private readonly PinCheckService $service,
    ) {}

    public function run(array $args): int
    {
        $tier = null;
        $actual = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--tier=')) {
                $tier = substr($arg, 7);
            } elseif (str_starts_with($arg, '--actual-model-id=')) {
                $actual = substr($arg, 18);
            }
        }

        if ($tier === null || $actual === null) {
            fwrite(STDERR, "Required: --tier= --actual-model-id=\n");
            return 2;
        }

        $state = $this->manager->load();
        $expected = $state->pinnedModels[$tier] ?? null;
        if ($expected === null) {
            fwrite(STDERR, "Tier not pinned: {$tier}\n");
            return 2;
        }

        $result = $this->service->verify($tier, $expected, $actual);

        echo json_encode(
            $result->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n";

        return $result->match ? 0 : 1;
    }
}
