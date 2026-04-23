<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Execution;

final class CrashDumper
{
    public function __construct(private readonly string $outputDir) {}

    public function dump(CrashContext $context): string
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }

        $filename = sprintf(
            'runner-crash-%s.json',
            str_replace([':', '-'], '', substr($context->abortedAt, 0, 19)),
        );
        $path = $this->outputDir . '/' . $filename;

        $payload = [
            'aborted_at' => $context->abortedAt,
            'reason' => $context->reason,
            'runs_completed_before_abort' => $context->runsCompletedBeforeAbort,
            'runs_remaining' => $context->runsRemaining,
            'last_5_errors' => $context->errors,
            'state_snapshot' => $context->stateSnapshot->toArray(),
            'environment' => $context->environment,
        ];

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        );

        return $path;
    }
}
