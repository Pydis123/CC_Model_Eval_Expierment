<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests;

use LlmDispatch\Runner\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testLoadsExperimentConfigFromFile(): void
    {
        $config = Config::fromFile($this->writeTempConfig([]));

        $this->assertSame(1, $config->schemaVersion);
        $this->assertSame('t', $config->experimentName);
        $this->assertSame(42, $config->planSeed);
        $this->assertSame(5, $config->nReplicates);
        $this->assertSame(3, $config->maxIterationsPerRun);
        $this->assertSame(1800, $config->maxWallClockSeconds);
    }

    public function testExposesTierList(): void
    {
        $path = $this->writeTempConfig(['tiers' => ['haiku', 'sonnet']]);
        $this->assertSame(['haiku', 'sonnet'], Config::fromFile($path)->tiers);
    }

    public function testExposesTaskIdList(): void
    {
        $path = $this->writeTempConfig(['task_ids' => ['101-a', '108-b']]);
        $this->assertSame(['101-a', '108-b'], Config::fromFile($path)->taskIds);
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

    public function testReadsJudgeModelWhenPresent(): void
    {
        $path = $this->writeTempConfig(['judge_model' => 'claude-opus-4-8']);
        $this->assertSame('claude-opus-4-8', Config::fromFile($path)->judgeModel);
    }

    public function testJudgeModelDefaultsToNull(): void
    {
        $path = $this->writeTempConfig([]);
        $this->assertNull(Config::fromFile($path)->judgeModel);
    }

    public function testReadsEffortPerTierWhenPresent(): void
    {
        $path = $this->writeTempConfig(['effort' => ['haiku' => 'high']]);
        $this->assertSame(['haiku' => 'high'], Config::fromFile($path)->effort);
    }

    public function testEffortDefaultsToEmpty(): void
    {
        $path = $this->writeTempConfig([]);
        $this->assertSame([], Config::fromFile($path)->effort);
    }

    /** @param array<string, mixed> $overrides */
    private function writeTempConfig(array $overrides): string
    {
        $base = [
            'schema_version' => 1, 'experiment_name' => 't', 'plan_seed' => 42,
            'n_replicates' => 5, 'max_iterations_per_run' => 3,
            'max_wall_clock_seconds' => 1800, 'tiers' => ['haiku'],
            'task_ids' => ['001-x'],
            'pinned_models' => ['haiku' => null],
            'policy' => 'retry-only',
            'db' => ['host' => 'h', 'port' => 1, 'database' => 'd', 'user' => 'u', 'password' => 'p'],
        ];
        $path = sys_get_temp_dir() . '/cfg-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode(array_merge($base, $overrides), JSON_THROW_ON_ERROR));
        return $path;
    }
}
