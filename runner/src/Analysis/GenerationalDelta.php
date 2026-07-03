<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

final class GenerationalDelta
{
    /**
     * @param list<array<string, mixed>> $oldRows
     * @param list<array<string, mixed>> $newRows
     * @return array<string, array{old_tokens: float, new_tokens: float, delta_pct: float, old_pass: float, new_pass: float}>
     */
    public static function compute(array $oldRows, array $newRows, string $tier): array
    {
        $old = self::byTask($oldRows, $tier);
        $new = self::byTask($newRows, $tier);

        $result = [];
        foreach ($old as $taskId => $oldStats) {
            if (!isset($new[$taskId])) {
                continue;
            }
            $newStats = $new[$taskId];
            $deltaPct = $oldStats['tokens'] > 0.0
                ? (($newStats['tokens'] - $oldStats['tokens']) / $oldStats['tokens']) * 100.0
                : 0.0;
            $result[$taskId] = [
                'old_tokens' => $oldStats['tokens'],
                'new_tokens' => $newStats['tokens'],
                'delta_pct' => $deltaPct,
                'old_pass' => $oldStats['pass'],
                'new_pass' => $newStats['pass'],
            ];
        }
        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array{tokens: float, pass: float}>
     */
    private static function byTask(array $rows, string $tier): array
    {
        $grouped = [];
        foreach ($rows as $r) {
            if ((string) ($r['model_tier'] ?? '') !== $tier) {
                continue;
            }
            $taskId = (string) ($r['task_id'] ?? '');
            $grouped[$taskId][] = $r;
        }

        $stats = [];
        foreach ($grouped as $taskId => $taskRows) {
            $n = count($taskRows);
            $tokens = array_sum(array_map(
                static fn($r) => (int) ($r['tokens_subagent_in'] ?? 0) + (int) ($r['tokens_subagent_out'] ?? 0),
                $taskRows,
            )) / $n;
            $pass = count(array_filter($taskRows, static fn($r) => ($r['outcome'] ?? '') === 'passed')) / $n;
            $stats[$taskId] = ['tokens' => $tokens, 'pass' => $pass];
        }
        return $stats;
    }
}
