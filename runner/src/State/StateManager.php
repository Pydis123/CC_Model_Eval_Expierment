<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\State;

use RuntimeException;

final class StateManager
{
    public function __construct(private readonly string $path) {}

    public function load(): State
    {
        if (!is_file($this->path)) {
            return State::empty();
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException("Cannot read state file: {$this->path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return State::fromArray($data);
    }

    public function save(State $state): void
    {
        $json = json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $written = file_put_contents($this->path, $json . "\n");
        if ($written === false) {
            throw new RuntimeException("Cannot write state file: {$this->path}");
        }
    }

    public function claimNext(string $nowIso): ?Run
    {
        $state = $this->load();

        foreach ($state->remainingRuns as $run) {
            if ($run->claimedAt === null) {
                $updated = $run->withClaimedAt($nowIso);
                $this->save($state->replaceRun($updated));
                return $updated;
            }
        }

        return null;
    }
}
