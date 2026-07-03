<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

final class SwapDetector
{
    /** @var array<string, array{id: string, count: int}> */
    private array $streaks = [];
    private ?string $haltReason = null;

    public function __construct(private readonly int $threshold) {}

    public function record(string $tier, string $reportedId, string $expectedId): void
    {
        if ($reportedId === '' || $reportedId === $expectedId) {
            unset($this->streaks[$tier]);
            return;
        }

        $current = $this->streaks[$tier] ?? null;
        if ($current !== null && $current['id'] === $reportedId) {
            $count = $current['count'] + 1;
        } else {
            $count = 1;
        }
        $this->streaks[$tier] = ['id' => $reportedId, 'count' => $count];

        if ($count >= $this->threshold) {
            $this->haltReason = sprintf(
                'Suspected silent model swap: tier "%s" reported "%s" (expected "%s") %d times consecutively.',
                $tier,
                $reportedId,
                $expectedId,
                $count,
            );
        }
    }

    public function shouldHalt(): bool
    {
        return $this->haltReason !== null;
    }

    public function haltReason(): ?string
    {
        return $this->haltReason;
    }
}
