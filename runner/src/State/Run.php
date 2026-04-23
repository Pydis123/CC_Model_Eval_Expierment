<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\State;

final class Run
{
    public function __construct(
        public readonly string $runId,
        public readonly string $taskId,
        public readonly string $modelTier,
        public readonly int $n,
        public readonly ?string $claimedAt = null,
    ) {}

    public function withClaimedAt(string $timestamp): self
    {
        return new self($this->runId, $this->taskId, $this->modelTier, $this->n, $timestamp);
    }

    /**
     * @return array{run_id: string, task_id: string, model_tier: string, n: int, claimed_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'task_id' => $this->taskId,
            'model_tier' => $this->modelTier,
            'n' => $this->n,
            'claimed_at' => $this->claimedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            runId: (string) $data['run_id'],
            taskId: (string) $data['task_id'],
            modelTier: (string) $data['model_tier'],
            n: (int) $data['n'],
            claimedAt: isset($data['claimed_at']) ? (string) $data['claimed_at'] : null,
        );
    }
}
