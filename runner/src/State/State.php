<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\State;

final class State
{
    /**
     * @param list<Run> $remainingRuns
     * @param list<Run> $completedRuns
     * @param array<string, string>|null $pinnedModels
     */
    public function __construct(
        public readonly int $schemaVersion,
        public readonly ?string $experimentStartedAt,
        public readonly ?array $pinnedModels,
        public readonly int $nextRunIdx,
        public readonly array $completedRuns,
        public readonly array $remainingRuns,
        public readonly int $currentSessionTokensEst,
        public readonly ?string $currentSessionStartedAt,
    ) {}

    public static function empty(): self
    {
        return new self(
            schemaVersion: 1,
            experimentStartedAt: null,
            pinnedModels: null,
            nextRunIdx: 0,
            completedRuns: [],
            remainingRuns: [],
            currentSessionTokensEst: 0,
            currentSessionStartedAt: null,
        );
    }

    /**
     * @param list<Run> $runs
     */
    public function withRemainingRuns(array $runs): self
    {
        return new self(
            $this->schemaVersion,
            $this->experimentStartedAt,
            $this->pinnedModels,
            $this->nextRunIdx,
            $this->completedRuns,
            $runs,
            $this->currentSessionTokensEst,
            $this->currentSessionStartedAt,
        );
    }

    public function moveToCompleted(string $runId): self
    {
        $remaining = [];
        $moved = null;
        foreach ($this->remainingRuns as $run) {
            if ($run->runId === $runId) {
                $moved = $run;
            } else {
                $remaining[] = $run;
            }
        }

        $completed = $this->completedRuns;
        if ($moved !== null) {
            $completed[] = $moved;
        }

        return new self(
            $this->schemaVersion,
            $this->experimentStartedAt,
            $this->pinnedModels,
            $this->nextRunIdx + 1,
            $completed,
            $remaining,
            $this->currentSessionTokensEst,
            $this->currentSessionStartedAt,
        );
    }

    public function requeueCompleted(string $runId): self
    {
        $completed = [];
        $moved = null;
        foreach ($this->completedRuns as $run) {
            if ($run->runId === $runId) {
                $moved = $run;
            } else {
                $completed[] = $run;
            }
        }

        $remaining = $this->remainingRuns;
        if ($moved !== null) {
            // Reset claimedAt to null and prepend to remaining
            $remaining = [new Run($moved->runId, $moved->taskId, $moved->modelTier, $moved->n, null), ...$remaining];
        }

        return new self(
            $this->schemaVersion,
            $this->experimentStartedAt,
            $this->pinnedModels,
            $this->nextRunIdx,
            $completed,
            $remaining,
            $this->currentSessionTokensEst,
            $this->currentSessionStartedAt,
        );
    }

    /**
     * @param array<string, string> $models
     */
    public function withPinnedModels(array $models): self
    {
        return new self(
            $this->schemaVersion,
            $this->experimentStartedAt,
            $models,
            $this->nextRunIdx,
            $this->completedRuns,
            $this->remainingRuns,
            $this->currentSessionTokensEst,
            $this->currentSessionStartedAt,
        );
    }

    public function replaceRun(Run $updated): self
    {
        $remaining = [];
        foreach ($this->remainingRuns as $run) {
            $remaining[] = $run->runId === $updated->runId ? $updated : $run;
        }
        return $this->withRemainingRuns($remaining);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'experiment_started_at' => $this->experimentStartedAt,
            'pinned_models' => $this->pinnedModels,
            'next_run_idx' => $this->nextRunIdx,
            'completed_runs' => array_map(static fn(Run $r) => $r->toArray(), $this->completedRuns),
            'remaining_runs' => array_map(static fn(Run $r) => $r->toArray(), $this->remainingRuns),
            'current_session_tokens_est' => $this->currentSessionTokensEst,
            'current_session_started_at' => $this->currentSessionStartedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schemaVersion: (int) ($data['schema_version'] ?? 1),
            experimentStartedAt: isset($data['experiment_started_at']) ? (string) $data['experiment_started_at'] : null,
            pinnedModels: isset($data['pinned_models']) && is_array($data['pinned_models'])
                ? array_map(static fn($v) => (string) $v, $data['pinned_models'])
                : null,
            nextRunIdx: (int) ($data['next_run_idx'] ?? 0),
            completedRuns: array_map(
                static fn(array $r) => Run::fromArray($r),
                (array) ($data['completed_runs'] ?? [])
            ),
            remainingRuns: array_map(
                static fn(array $r) => Run::fromArray($r),
                (array) ($data['remaining_runs'] ?? [])
            ),
            currentSessionTokensEst: (int) ($data['current_session_tokens_est'] ?? 0),
            currentSessionStartedAt: isset($data['current_session_started_at']) ? (string) $data['current_session_started_at'] : null,
        );
    }
}
