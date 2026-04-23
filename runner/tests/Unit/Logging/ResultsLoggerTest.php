<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Logging;

use LlmDispatch\Runner\Logging\ResultsLogger;
use PHPUnit\Framework\TestCase;

final class ResultsLoggerTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = sys_get_temp_dir() . '/results_' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    public function testAppendsOneLinePerCall(): void
    {
        $logger = new ResultsLogger($this->tmpPath);

        $logger->append(['run_id' => 'a', 'outcome' => 'passed']);
        $logger->append(['run_id' => 'b', 'outcome' => 'failed']);

        $lines = array_values(array_filter(explode("\n", file_get_contents($this->tmpPath))));
        $this->assertCount(2, $lines);

        $this->assertSame(['run_id' => 'a', 'outcome' => 'passed'], json_decode($lines[0], true));
        $this->assertSame(['run_id' => 'b', 'outcome' => 'failed'], json_decode($lines[1], true));
    }

    public function testCreatesFileIfMissing(): void
    {
        $this->assertFileDoesNotExist($this->tmpPath);

        $logger = new ResultsLogger($this->tmpPath);
        $logger->append(['run_id' => 'a']);

        $this->assertFileExists($this->tmpPath);
    }

    public function testEncodesUnicodeWithoutEscaping(): void
    {
        $logger = new ResultsLogger($this->tmpPath);

        $logger->append(['name' => 'Ärendesystem']);

        $content = file_get_contents($this->tmpPath);
        $this->assertStringContainsString('Ärendesystem', $content);
        $this->assertStringNotContainsString('\\u00c4', $content);
    }

    public function testAppendsWithoutReadingExistingContent(): void
    {
        file_put_contents($this->tmpPath, "{\"existing\":\"row\"}\n");

        $logger = new ResultsLogger($this->tmpPath);
        $logger->append(['new' => 'row']);

        $lines = array_values(array_filter(explode("\n", file_get_contents($this->tmpPath))));
        $this->assertCount(2, $lines);
        $this->assertSame(['existing' => 'row'], json_decode($lines[0], true));
        $this->assertSame(['new' => 'row'], json_decode($lines[1], true));
    }
}
