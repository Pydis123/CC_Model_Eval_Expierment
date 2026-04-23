<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\TaskPromptLoader;
use PHPUnit\Framework\TestCase;

final class TaskPromptLoaderTest extends TestCase
{
    private string $tasksDir;

    protected function setUp(): void
    {
        $this->tasksDir = sys_get_temp_dir() . '/tasks_' . uniqid();
        mkdir($this->tasksDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tasksDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->tasksDir);
    }

    public function testLoadsPromptAndTaskMeta(): void
    {
        file_put_contents($this->tasksDir . '/001.json', json_encode([
            'id' => '001',
            'prompt_file' => '001.prompt.md',
            'max_iterations' => 3,
            'max_wall_clock_s' => 900,
            'success_criteria' => [['type' => 'phpunit']],
        ]));
        file_put_contents($this->tasksDir . '/001.prompt.md', 'Please implement the thing.');

        $loaded = (new TaskPromptLoader($this->tasksDir))->load('001');

        $this->assertSame('Please implement the thing.', $loaded->prompt);
        $this->assertSame(3, $loaded->maxIterations);
        $this->assertSame(900, $loaded->maxWallClockS);
        $this->assertSame('001', $loaded->taskDef['id']);
    }

    public function testThrowsIfTaskJsonMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/task file missing/i');

        (new TaskPromptLoader($this->tasksDir))->load('nonexistent');
    }

    public function testThrowsIfPromptFileMissing(): void
    {
        file_put_contents($this->tasksDir . '/001.json', json_encode([
            'id' => '001',
            'prompt_file' => '001.prompt.md',
            'max_iterations' => 3,
            'max_wall_clock_s' => 900,
            'success_criteria' => [],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/prompt file missing/i');

        (new TaskPromptLoader($this->tasksDir))->load('001');
    }
}
