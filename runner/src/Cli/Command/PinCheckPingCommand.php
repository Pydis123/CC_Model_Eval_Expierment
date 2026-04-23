<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\State\StateManager;

final class PinCheckPingCommand implements CommandInterface
{
    public function __construct(private readonly StateManager $manager) {}

    public function run(array $args): int
    {
        $tier = null;
        $modelId = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--tier=')) {
                $tier = substr($arg, 7);
            } elseif (str_starts_with($arg, '--model-id=')) {
                $modelId = substr($arg, 11);
            }
        }

        if ($tier === null || $modelId === null) {
            fwrite(STDERR, "Required: --tier= --model-id=\n");
            return 2;
        }

        $state = $this->manager->load();
        $pinned = $state->pinnedModels ?? [];
        $pinned[$tier] = $modelId;
        $this->manager->save($state->withPinnedModels($pinned));

        echo json_encode([
            'tier' => $tier,
            'model_id' => $modelId,
            'stored' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        return 0;
    }
}
