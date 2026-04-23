<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\State\StateManager;

final class StatePinModelsCommand implements CommandInterface
{
    public function __construct(private readonly StateManager $manager) {}

    public function run(array $args): int
    {
        $force = in_array('--force', $args, true);

        $models = [];
        foreach (['haiku', 'sonnet', 'opus'] as $tier) {
            foreach ($args as $arg) {
                $prefix = "--{$tier}=";
                if (str_starts_with($arg, $prefix)) {
                    $models[$tier] = substr($arg, strlen($prefix));
                    break;
                }
            }
        }

        if (count($models) !== 3) {
            fwrite(STDERR, "Missing tier flag(s). Required: --haiku=, --sonnet=, --opus=\n");
            return 2;
        }

        $state = $this->manager->load();
        if ($state->pinnedModels !== null && !$force) {
            fwrite(STDERR, "Models already pinned. Use --force to overwrite.\n");
            return 2;
        }

        $this->manager->save($state->withPinnedModels($models));

        echo json_encode(['pinned_models' => $models], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        return 0;
    }
}
