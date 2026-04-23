<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Report;

use LlmDispatch\Runner\Report\MarkdownBuilder;
use PHPUnit\Framework\TestCase;

final class MarkdownBuilderTest extends TestCase
{
    public function testH1AndH2AndH3(): void
    {
        $md = new MarkdownBuilder();
        $md->h1('Title');
        $md->h2('Section');
        $md->h3('Subsection');

        $this->assertSame("# Title\n\n## Section\n\n### Subsection\n\n", $md->build());
    }

    public function testParagraph(): void
    {
        $md = new MarkdownBuilder();
        $md->paragraph('Hello world.');

        $this->assertSame("Hello world.\n\n", $md->build());
    }

    public function testTable(): void
    {
        $md = new MarkdownBuilder();
        $md->table(
            headers: ['Col A', 'Col B'],
            rows: [
                ['1', 'x'],
                ['2', 'y'],
            ],
        );

        $expected = "| Col A | Col B |\n| --- | --- |\n| 1 | x |\n| 2 | y |\n\n";
        $this->assertSame($expected, $md->build());
    }

    public function testFencedCode(): void
    {
        $md = new MarkdownBuilder();
        $md->fencedCode('bash', "ls\npwd\n");

        $this->assertSame("```bash\nls\npwd\n```\n\n", $md->build());
    }

    public function testHorizontalRule(): void
    {
        $md = new MarkdownBuilder();
        $md->hr();

        $this->assertSame("---\n\n", $md->build());
    }

    public function testChaining(): void
    {
        $md = (new MarkdownBuilder())
            ->h1('A')
            ->paragraph('B');

        $this->assertSame("# A\n\nB\n\n", $md->build());
    }
}
