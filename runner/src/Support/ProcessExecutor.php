<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Support;

use RuntimeException;

class ProcessExecutor
{
    /**
     * @param list<string> $command  Argv-style command + args.
     */
    public function exec(string $cwd, array $command): ProcessResult
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process: ' . implode(' ', $command));
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult($exitCode, $stdout, $stderr);
    }
}
