<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

use RuntimeException;

final class TaskPromptLoader
{
    public function __construct(private readonly string $tasksDir) {}

    public function load(string $taskId): LoadedTask
    {
        $jsonPath = $this->tasksDir . '/' . $taskId . '.json';
        if (!is_file($jsonPath)) {
            throw new RuntimeException("Task file missing: {$jsonPath}");
        }

        /** @var array<string, mixed> $taskDef */
        $taskDef = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

        $promptFile = (string) ($taskDef['prompt_file'] ?? '');
        $promptPath = $this->tasksDir . '/' . $promptFile;
        if (!is_file($promptPath)) {
            throw new RuntimeException("Prompt file missing: {$promptPath}");
        }

        return new LoadedTask(
            taskId: $taskId,
            prompt: (string) file_get_contents($promptPath),
            maxIterations: (int) ($taskDef['max_iterations'] ?? 3),
            maxWallClockS: (int) ($taskDef['max_wall_clock_s'] ?? 1800),
            taskDef: $taskDef,
        );
    }
}
