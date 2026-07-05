<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Analysis\GenerationalDelta;
use LlmDispatch\Runner\Cli\CommandInterface;

final class ReportDeltaCommand implements CommandInterface
{
    public function __construct(
        private readonly string $oldPath,
        private readonly string $newPath,
        private readonly string $outputPath,
    ) {}

    public function run(array $args): int
    {
        $old = $this->readJsonl($this->oldPath);
        $new = $this->readJsonl($this->newPath);
        if ($old === [] || $new === []) {
            fwrite(STDERR, "Both result files must be non-empty.\n");
            return 2;
        }

        $md = "# Generational delta (v1 → v2)\n\n";
        foreach (['sonnet', 'opus', 'haiku'] as $tier) {
            $delta = GenerationalDelta::compute($old, $new, $tier);
            if ($delta === []) {
                continue;
            }
            $md .= "## {$tier}\n\n";
            $md .= "| Task | Old tok | New tok | Δ% | Old pass | New pass |\n";
            $md .= "|---|--:|--:|--:|--:|--:|\n";
            foreach ($delta as $taskId => $d) {
                $md .= sprintf(
                    "| %s | %s | %s | %+.1f%% | %.0f%% | %.0f%% |\n",
                    $taskId,
                    number_format($d['old_tokens'], 0),
                    number_format($d['new_tokens'], 0),
                    $d['delta_pct'],
                    $d['old_pass'] * 100,
                    $d['new_pass'] * 100,
                );
            }
            $md .= "\n";
        }
        $md .= "> Haiku is the environment-drift control (same model id in v1 and v2).\n";

        file_put_contents($this->outputPath, $md);
        echo "Wrote {$this->outputPath}\n";
        return 0;
    }

    /** @return list<array<string, mixed>> */
    private function readJsonl(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        // Keyed by run_id so a re-run supersedes its earlier (error) row,
        // matching Aggregator's last-row-per-run_id rule.
        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[(string) ($decoded['run_id'] ?? count($rows))] = $decoded;
            }
        }
        return array_values($rows);
    }
}
