<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Findings;

use LlmDispatch\Runner\Evaluator\Findings\GroundTruth;
use PHPUnit\Framework\TestCase;

final class GroundTruthTest extends TestCase
{
    public function testFromFileThrowsRuntimeExceptionOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        GroundTruth::fromFile('/nonexistent/file.json');
    }

    public function testFromFileThrowsInvalidArgumentExceptionWhenDefectLacksMandatoryFields(): void
    {
        $path = sys_get_temp_dir() . '/gt-invalid-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode(['defects' => [
            ['id' => 'd1', 'file' => 'src/A.php', 'line' => 100],
            // Missing 'defect_class'
        ]], JSON_THROW_ON_ERROR));

        $this->expectException(\InvalidArgumentException::class);
        GroundTruth::fromFile($path);
    }
}
