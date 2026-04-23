<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;

final class StateResetStaleCommand implements CommandInterface
{
    /**
     * @param callable(): DateTimeImmutable $now
     */
    public function __construct(
        private readonly StateManager $stateManager,
        private $now,
    ) {}

    public function run(array $args): int
    {
        $olderThanRaw = $this->argValue($args, '--older-than=') ?? '2h';
        $dryRun = in_array('--dry-run', $args, true);

        $seconds = $this->parseDurationSeconds($olderThanRaw);
        $now = ($this->now)();
        $cutoff = $now->getTimestamp() - $seconds;

        $state = $this->stateManager->load();

        $updated = [];
        $cleared = 0;
        foreach ($state->remainingRuns as $run) {
            if ($run->claimedAt !== null) {
                $ts = (new DateTimeImmutable($run->claimedAt))->getTimestamp();
                if ($ts < $cutoff) {
                    $updated[] = new Run($run->runId, $run->taskId, $run->modelTier, $run->n, null);
                    $cleared++;
                    continue;
                }
            }
            $updated[] = $run;
        }

        echo json_encode(['cleared' => $cleared, 'dry_run' => $dryRun], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        if (!$dryRun) {
            $this->stateManager->save($state->withRemainingRuns($updated));
        }

        return 0;
    }

    public function parseDurationSeconds(string $raw): int
    {
        if (!preg_match('/^(\d+)([smhd])$/', $raw, $m)) {
            throw new InvalidArgumentException("Invalid duration format: {$raw}");
        }
        $n = (int) $m[1];
        return match ($m[2]) {
            's' => $n,
            'm' => $n * 60,
            'h' => $n * 3600,
            'd' => $n * 86400,
        };
    }

    /**
     * @param list<string> $args
     */
    private function argValue(array $args, string $prefix): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }
        return null;
    }
}
