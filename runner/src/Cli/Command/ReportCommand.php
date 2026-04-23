<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use DateTimeImmutable;
use DateTimeZone;
use LlmDispatch\Runner\Analysis\Aggregator;
use LlmDispatch\Runner\Analysis\BootstrapSimulator;
use LlmDispatch\Runner\Analysis\IncompleteResultsException;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Config;
use LlmDispatch\Runner\Report\FindingsWriter;
use RuntimeException;

final class ReportCommand implements CommandInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Aggregator $aggregator,
        private readonly BootstrapSimulator $simulator,
        private readonly FindingsWriter $writer,
        private readonly string $defaultInputPath,
        private readonly string $defaultOutputPath,
        private readonly int $defaultBootstrapSamples,
        private readonly int $defaultBootstrapSeed,
    ) {}

    public function run(array $args): int
    {
        $inputPath = $this->argValue($args, '--input=') ?? $this->defaultInputPath;
        $outputPath = $this->argValue($args, '--output=') ?? $this->defaultOutputPath;
        $samples = (int) ($this->argValue($args, '--bootstrap-samples=') ?? $this->defaultBootstrapSamples);
        $seed = (int) ($this->argValue($args, '--bootstrap-seed=') ?? $this->defaultBootstrapSeed);

        try {
            $matrix = $this->aggregator->aggregate($inputPath, $this->config);
        } catch (IncompleteResultsException $e) {
            fwrite(STDERR, "Incomplete results: {$e->getMessage()}\n");
            return 1;
        } catch (RuntimeException $e) {
            fwrite(STDERR, "Error: {$e->getMessage()}\n");
            return 2;
        }

        $simulation = $this->simulator->simulate(
            matrix: $matrix,
            taskIds: $this->config->taskIds,
            tiers: $this->config->tiers,
            samples: $samples,
            seed: $seed,
        );

        $rowCount = $this->countRows($matrix);
        $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        $md = $this->writer->render(
            matrix: $matrix,
            simulation: $simulation,
            config: $this->config,
            sourcePath: $inputPath,
            rowCount: $rowCount,
            generatedAt: $generatedAt,
        );

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($outputPath, $md);

        echo json_encode(
            ['written' => $outputPath, 'rows' => $rowCount, 'samples' => $samples, 'seed' => $seed],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";

        return 0;
    }

    /**
     * @param array<string, array<string, \LlmDispatch\Runner\Analysis\CellStats>> $matrix
     */
    private function countRows(array $matrix): int
    {
        $count = 0;
        foreach ($matrix as $taskRow) {
            foreach ($taskRow as $cell) {
                $count += $cell->nRuns;
            }
        }
        return $count;
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
