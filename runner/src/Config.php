<?php

declare(strict_types=1);

namespace LlmDispatch\Runner;

final class Config
{
    /**
     * @param list<string> $tiers
     * @param list<string> $taskIds
     * @param array<string, string|null> $pinnedModels
     * @param array<string, mixed> $db
     */
    public function __construct(
        public readonly int $schemaVersion,
        public readonly string $experimentName,
        public readonly int $planSeed,
        public readonly int $nReplicates,
        public readonly int $maxIterationsPerRun,
        public readonly int $maxWallClockSeconds,
        public readonly array $tiers,
        public readonly array $taskIds,
        public readonly array $pinnedModels,
        public readonly string $policy,
        public readonly array $db,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file does not exist: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read config file: {$path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            schemaVersion: (int) $data['schema_version'],
            experimentName: (string) $data['experiment_name'],
            planSeed: (int) $data['plan_seed'],
            nReplicates: (int) $data['n_replicates'],
            maxIterationsPerRun: (int) $data['max_iterations_per_run'],
            maxWallClockSeconds: (int) $data['max_wall_clock_seconds'],
            tiers: array_values((array) $data['tiers']),
            taskIds: array_values((array) $data['task_ids']),
            pinnedModels: (array) $data['pinned_models'],
            policy: (string) $data['policy'],
            db: (array) $data['db'],
        );
    }
}
