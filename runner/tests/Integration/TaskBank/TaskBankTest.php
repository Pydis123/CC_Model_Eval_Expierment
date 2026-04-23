<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Integration\TaskBank;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskBankTest extends TestCase
{
    private const KNOWN_CATEGORIES = [
        'trivial_i18n',
        'crud_addition',
        'query_optimization',
        'migration_backfill',
        'refactor',
        'bugfix_root_cause',
        'route_rbac',
        'frontend_alpine',
    ];

    private const KNOWN_CHECK_TYPES = [
        'phpunit',
        'query_count',
        'smoke_no_regressions',
        'lint',
        'file_exists',
        'grep_not_present',
        'diff_size_limit',
    ];

    private const KNOWN_SIZES = ['xs', 's', 'm', 'l'];

    public function testTasksDirectoryExists(): void
    {
        $dir = $this->tasksDir();
        $this->assertDirectoryExists($dir, 'tasks/ directory missing');
    }

    public function testSchemaJsonExists(): void
    {
        $this->assertFileExists($this->tasksDir() . '/schema.json');
    }

    public function testReadmeExists(): void
    {
        $this->assertFileExists($this->tasksDir() . '/README.md');
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskJsonParses(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $raw = file_get_contents($jsonPath);
        $this->assertNotFalse($raw, "Cannot read {$jsonPath}");

        $data = json_decode($raw, true);
        $this->assertIsArray($data, "Invalid JSON in {$jsonPath}");
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskJsonHasRequiredFields(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);

        foreach (['id', 'category', 'size_estimate', 'prompt_file', 'max_iterations', 'max_wall_clock_s', 'success_criteria'] as $field) {
            $this->assertArrayHasKey($field, $data, "{$jsonPath} missing required field: {$field}");
        }
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskIdMatchesFilename(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);
        $expected = basename($jsonPath, '.json');

        $this->assertSame($expected, $data['id'], "id field must match filename in {$jsonPath}");
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskCategoryIsKnown(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);

        $this->assertContains($data['category'], self::KNOWN_CATEGORIES, "Unknown category in {$jsonPath}");
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskSizeIsKnown(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);

        $this->assertContains($data['size_estimate'], self::KNOWN_SIZES, "Unknown size in {$jsonPath}");
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskPromptFileExists(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);
        $promptPath = dirname($jsonPath) . '/' . $data['prompt_file'];

        $this->assertFileExists($promptPath, "Prompt file missing: {$promptPath}");
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskMaxIterationsIsInRange(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);

        $this->assertGreaterThanOrEqual(1, $data['max_iterations']);
        $this->assertLessThanOrEqual(10, $data['max_iterations']);
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskMaxWallClockIsInRange(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);

        $this->assertGreaterThanOrEqual(60, $data['max_wall_clock_s']);
        $this->assertLessThanOrEqual(7200, $data['max_wall_clock_s']);
    }

    #[DataProvider('provideTaskFiles')]
    public function testTaskSuccessCriteriaTypesAreKnown(string $jsonPath): void
    {
        if ($jsonPath === '__skip__') {
            $this->markTestSkipped('No task JSON files in tasks/ yet');
        }

        $data = $this->loadJson($jsonPath);

        $this->assertIsArray($data['success_criteria']);
        $this->assertNotEmpty($data['success_criteria'], "success_criteria must not be empty in {$jsonPath}");

        foreach ($data['success_criteria'] as $idx => $criterion) {
            $this->assertArrayHasKey('type', $criterion, "Criterion #{$idx} missing type in {$jsonPath}");
            $this->assertContains(
                $criterion['type'],
                self::KNOWN_CHECK_TYPES,
                "Unknown check type '{$criterion['type']}' in {$jsonPath}"
            );
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTaskFiles(): iterable
    {
        $dir = dirname(__DIR__, 4) . '/tasks';
        if (!is_dir($dir)) {
            return;
        }

        $files = array_filter(
            glob($dir . '/*.json') ?: [],
            static fn(string $f): bool => basename($f) !== 'schema.json',
        );

        if ($files === []) {
            // No task JSON files exist yet — yield a sentinel so PHPUnit does
            // not error on an empty data set; the test body skips immediately.
            yield '__no_tasks__' => ['__skip__'];
            return;
        }

        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        return $data;
    }

    private function tasksDir(): string
    {
        return dirname(__DIR__, 4) . '/tasks';
    }
}
