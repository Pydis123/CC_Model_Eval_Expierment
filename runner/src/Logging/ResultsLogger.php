<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Logging;

use RuntimeException;

final class ResultsLogger
{
    public function __construct(private readonly string $path) {}

    /**
     * @param array<string, mixed> $row
     */
    public function append(array $row): void
    {
        $line = json_encode(
            $row,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $handle = fopen($this->path, 'a');
        if ($handle === false) {
            throw new RuntimeException("Cannot open results file: {$this->path}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("Cannot lock results file: {$this->path}");
            }
            try {
                $written = fwrite($handle, $line);
                if ($written === false) {
                    throw new RuntimeException("Cannot write to results file: {$this->path}");
                }
                fflush($handle);
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
