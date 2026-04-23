<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\FileExistsCheck;
use PHPUnit\Framework\TestCase;

final class FileExistsCheckTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/fex_' . uniqid();
        mkdir($this->tmpRoot . '/a/b', 0777, true);
        touch($this->tmpRoot . '/a/b/foo.txt');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpRoot . '/a/b/foo.txt');
        @rmdir($this->tmpRoot . '/a/b');
        @rmdir($this->tmpRoot . '/a');
        @rmdir($this->tmpRoot);
    }

    public function testPassesWhenAllPathsExist(): void
    {
        $check = new FileExistsCheck(['a/b/foo.txt']);

        $result = $check->run($this->tmpRoot);

        $this->assertTrue($result->passed);
        $this->assertSame('file_exists', $result->type);
        $this->assertSame([], $result->details['missing']);
    }

    public function testFailsWhenAnyPathMissing(): void
    {
        $check = new FileExistsCheck(['a/b/foo.txt', 'a/b/bar.txt']);

        $result = $check->run($this->tmpRoot);

        $this->assertFalse($result->passed);
        $this->assertSame(['a/b/bar.txt'], $result->details['missing']);
    }
}
