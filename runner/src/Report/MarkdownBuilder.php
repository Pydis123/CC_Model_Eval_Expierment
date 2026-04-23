<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Report;

final class MarkdownBuilder
{
    private string $buffer = '';

    public function h1(string $text): self
    {
        $this->buffer .= "# {$text}\n\n";
        return $this;
    }

    public function h2(string $text): self
    {
        $this->buffer .= "## {$text}\n\n";
        return $this;
    }

    public function h3(string $text): self
    {
        $this->buffer .= "### {$text}\n\n";
        return $this;
    }

    public function paragraph(string $text): self
    {
        $this->buffer .= "{$text}\n\n";
        return $this;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): self
    {
        $this->buffer .= '| ' . implode(' | ', $headers) . " |\n";
        $this->buffer .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";
        foreach ($rows as $row) {
            $this->buffer .= '| ' . implode(' | ', $row) . " |\n";
        }
        $this->buffer .= "\n";
        return $this;
    }

    public function fencedCode(string $lang, string $code): self
    {
        $this->buffer .= "```{$lang}\n{$code}```\n\n";
        return $this;
    }

    public function hr(): self
    {
        $this->buffer .= "---\n\n";
        return $this;
    }

    public function build(): string
    {
        return $this->buffer;
    }
}
