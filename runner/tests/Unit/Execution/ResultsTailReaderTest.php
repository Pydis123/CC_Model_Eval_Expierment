<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Execution;

use LlmDispatch\Runner\Execution\ResultsTailReader;
use PHPUnit\Framework\TestCase;

final class ResultsTailReaderTest extends TestCase
{
    public function testReturnsLastRow(): void
    {
        $path = sys_get_temp_dir() . '/tail-' . uniqid() . '.jsonl';
        file_put_contents($path, "{\"n\":1}\n{\"n\":2}\n");
        $this->assertSame(2, (new ResultsTailReader($path))->last()['n']);
        unlink($path);
    }

    public function testReturnsNullForMissingFile(): void
    {
        $this->assertNull((new ResultsTailReader('/no/such/file.jsonl'))->last());
    }
}
