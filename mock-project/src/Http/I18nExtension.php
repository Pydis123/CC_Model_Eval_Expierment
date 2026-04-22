<?php

declare(strict_types=1);

namespace App\Http;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class I18nExtension extends AbstractExtension
{
    /**
     * @param array<string,string> $strings
     */
    public function __construct(private array $strings) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->t(...)),
            new TwigFunction('tp', $this->tp(...)),
        ];
    }

    /**
     * @param array<string, string|int> $params
     */
    public function t(string $key, array $params = []): string
    {
        $value = $this->strings[$key] ?? $key;
        foreach ($params as $paramKey => $paramValue) {
            $value = str_replace('%' . $paramKey . '%', (string) $paramValue, $value);
        }
        return $value;
    }

    public function tp(string $key, int $count): string
    {
        $form = match (true) {
            $count === 0 => 'zero',
            $count === 1 => 'one',
            default => 'other',
        };
        $fullKey = $key . '.' . $form;
        $value = $this->strings[$fullKey] ?? $fullKey;
        return str_replace('%count%', (string) $count, $value);
    }
}
