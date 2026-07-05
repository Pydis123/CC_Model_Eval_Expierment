<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Support;

use LlmDispatch\Runner\Support\ProcessExecutor;
use PHPUnit\Framework\TestCase;

final class ProcessExecutorTest extends TestCase
{
    public function testPassesCustomEnvironmentToChild(): void
    {
        $executor = new ProcessExecutor();

        $result = $executor->exec(
            __DIR__,
            ['/bin/sh', '-c', 'echo "$MARKER-$PATH"'],
            ['MARKER' => 'isolated', 'PATH' => '/usr/bin:/bin'],
        );

        $this->assertStringContainsString('isolated-/usr/bin:/bin', $result->stdout);
    }

    public function testNullEnvInheritsParent(): void
    {
        $executor = new ProcessExecutor();

        $result = $executor->exec(
            __DIR__,
            ['/bin/sh', '-c', 'echo $HOME'],
        );

        $this->assertNotEmpty(trim($result->stdout));
    }
}
