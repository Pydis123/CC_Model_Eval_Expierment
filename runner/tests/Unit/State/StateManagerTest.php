<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\State;

use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\State;
use LlmDispatch\Runner\State\StateManager;
use PHPUnit\Framework\TestCase;

final class StateManagerTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = sys_get_temp_dir() . '/state_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    public function testLoadReturnsEmptyStateWhenFileMissing(): void
    {
        $manager = new StateManager($this->tmpPath);

        $state = $manager->load();

        $this->assertSame(1, $state->schemaVersion);
        $this->assertSame([], $state->remainingRuns);
    }

    public function testSaveAndReloadRoundtrip(): void
    {
        $manager = new StateManager($this->tmpPath);
        $state = State::empty()->withRemainingRuns([new Run('a', 't', 'opus', 2)]);

        $manager->save($state);
        $reloaded = $manager->load();

        $this->assertCount(1, $reloaded->remainingRuns);
        $this->assertSame('a', $reloaded->remainingRuns[0]->runId);
        $this->assertSame('opus', $reloaded->remainingRuns[0]->modelTier);
    }

    public function testSaveWritesPrettyPrintedJson(): void
    {
        $manager = new StateManager($this->tmpPath);
        $manager->save(State::empty());

        $content = file_get_contents($this->tmpPath);
        $this->assertStringContainsString("\n", $content);
        $this->assertJson($content);
    }
}
