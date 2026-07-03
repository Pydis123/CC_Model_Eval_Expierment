<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

final class ResultsTailReader
{
    public function __construct(private readonly string $path) {}

    /** @return array<string, mixed>|null */
    public function last(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            return null;
        }
        $decoded = json_decode((string) end($lines), true);
        return is_array($decoded) ? $decoded : null;
    }
}
