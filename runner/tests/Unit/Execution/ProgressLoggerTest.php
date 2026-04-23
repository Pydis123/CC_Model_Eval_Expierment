<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\ProgressLogger;
use PHPUnit\Framework\TestCase;

final class ProgressLoggerTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/progress_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
    }

    public function testWritesLineToFileAndStdout(): void
    {
        $logger = new ProgressLogger($this->logPath);

        ob_start();
        $logger->log('hello world');
        $stdout = ob_get_clean();

        $this->assertStringContainsString('hello world', $stdout);
        $this->assertStringContainsString('hello world', (string) file_get_contents($this->logPath));
    }

    public function testAppendsTimestampPrefix(): void
    {
        $logger = new ProgressLogger($this->logPath);

        ob_start();
        $logger->log('message');
        $stdout = ob_get_clean();

        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\] message/', $stdout);
    }

    public function testAppendsMultipleLines(): void
    {
        $logger = new ProgressLogger($this->logPath);

        ob_start();
        $logger->log('line one');
        $logger->log('line two');
        ob_end_clean();

        $content = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('line one', $content);
        $this->assertStringContainsString('line two', $content);
    }

    public function testCreatesLogDirIfMissing(): void
    {
        $nestedPath = sys_get_temp_dir() . '/progress_nested_' . uniqid() . '/runner.log';

        $logger = new ProgressLogger($nestedPath);

        ob_start();
        $logger->log('hi');
        ob_end_clean();

        $this->assertFileExists($nestedPath);

        @unlink($nestedPath);
        @rmdir(dirname($nestedPath));
    }
}
