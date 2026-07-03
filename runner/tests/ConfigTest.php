<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests;

use LlmDispatch\Runner\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../../experiment_config.json';

    public function testLoadsExperimentConfigFromFile(): void
    {
        $config = Config::fromFile(self::FIXTURE_PATH);

        $this->assertSame(1, $config->schemaVersion);
        $this->assertSame('llm-dispatch-v2-phase1', $config->experimentName);
        $this->assertSame(42, $config->planSeed);
        $this->assertSame(5, $config->nReplicates);
        $this->assertSame(3, $config->maxIterationsPerRun);
        $this->assertSame(1800, $config->maxWallClockSeconds);
    }

    public function testExposesTierList(): void
    {
        $config = Config::fromFile(self::FIXTURE_PATH);

        $this->assertSame(['haiku', 'sonnet', 'opus', 'fable'], $config->tiers);
    }

    public function testExposesTaskIdList(): void
    {
        $config = Config::fromFile(self::FIXTURE_PATH);

        $this->assertCount(8, $config->taskIds);
        $this->assertContains('001-i18n-status-flik', $config->taskIds);
        $this->assertContains('008-comment-composer-alpine', $config->taskIds);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/i');

        Config::fromFile('/nonexistent/path/to/config.json');
    }

    public function testThrowsOnInvalidJson(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cfg');
        file_put_contents($tmp, '{ not valid json');

        try {
            $this->expectException(\JsonException::class);
            Config::fromFile($tmp);
        } finally {
            unlink($tmp);
        }
    }
}
