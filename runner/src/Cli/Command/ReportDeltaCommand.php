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
        private readonly string $oldLabel,
        private readonly string $newLabel,
        private readonly ?string $footnote = null,
    ) {}

    public function run(array $args): int
    {
        $old = $this->readJsonl($this->oldPath);
        $new = $this->readJsonl($this->newPath);
        if ($old === [] || $new === []) {
            fwrite(STDERR, "Both result files must be non-empty.\n");
            return 2;
        }

        $md = "# Generational delta ({$this->oldLabel} → {$this->newLabel})\n\n";
        foreach (['sonnet', 'opus', 'haiku', 'fable'] as $tier) {
            $delta = GenerationalDelta::compute($old, $new, $tier);
            if ($delta === []) {
                continue;
            }
            $md .= "## {$tier}\n\n";
            $md .= "| Task | Old tok | New tok | Δ% | Old pass | New pass |\n";
            $md .= "|---|--:|--:|--:|--:|--:|\n";
            foreach ($delta as $taskId => $d) {
                $oldTokensStr = $d['old_tokens'] === null ? 'n/a' : number_format($d['old_tokens'], 0);
                $deltaPctStr = $d['delta_pct'] === null ? 'n/a' : sprintf('%+.1f%%', $d['delta_pct']);
                $oldPassStr = $d['old_pass'] === null ? 'n/a' : sprintf('%.0f%%', $d['old_pass'] * 100);
                $md .= sprintf(
                    "| %s | %s | %s | %s | %s | %.0f%% |\n",
                    $taskId,
                    $oldTokensStr,
                    number_format($d['new_tokens'], 0),
                    $deltaPctStr,
                    $oldPassStr,
                    $d['new_pass'] * 100,
                );
            }
            $md .= "\n";
        }
        if ($this->footnote !== null) {
            $md .= "> {$this->footnote}\n";
        }

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
