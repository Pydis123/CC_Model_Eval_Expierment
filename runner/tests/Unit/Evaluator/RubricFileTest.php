<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator;

use LlmDispatch\Runner\Evaluator\RubricFile;
use PHPUnit\Framework\TestCase;

final class RubricFileTest extends TestCase
{
    public function testLoadsValidCriteriaList(): void
    {
        $path = $this->rubricFile(['criteria' => [
            ['id' => 'a', 'text' => 'Criterion A', 'anchors' => ['0' => 'x', '1' => 'y', '2' => 'z']],
        ]]);

        $criteria = RubricFile::loadCriteria($path);

        self::assertSame([
            ['id' => 'a', 'text' => 'Criterion A', 'anchors' => [0 => 'x', 1 => 'y', 2 => 'z']],
        ], $criteria);
    }

    public function testReturnsEmptyArrayForMalformedJson(): void
    {
        $path = sys_get_temp_dir() . '/rubric_file_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($path, '{not valid json');

        self::assertSame([], RubricFile::loadCriteria($path));
    }

    public function testReturnsEmptyArrayWhenCriteriaKeyMissing(): void
    {
        $path = $this->rubricFile(['not_criteria' => []]);

        self::assertSame([], RubricFile::loadCriteria($path));
    }

    public function testReturnsEmptyArrayForUnreadableFile(): void
    {
        $path = '/nonexistent/rubric_' . bin2hex(random_bytes(6)) . '.json';

        self::assertSame([], RubricFile::loadCriteria($path));
    }

    /** @param array<string, mixed> $data */
    private function rubricFile(array $data): string
    {
        $path = sys_get_temp_dir() . '/rubric_file_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($path, (string) json_encode($data));
        return $path;
    }
}
