<?php

declare(strict_types=1);

namespace App\Http;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class I18nExtension extends AbstractExtension
{
    /** @var array<string, array<string,string>> cache per locale */
    private array $cache = [];

    /**
     * @param callable(string): array<string,string> $stringsLoader
     */
    public function __construct(private readonly mixed $stringsLoader)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->t(...)),
            new TwigFunction('tp', $this->tp(...)),
            new TwigFunction('current_locale', $this->currentLocale(...)),
        ];
    }

    /**
     * @param array<string, string|int> $params
     */
    public function t(string $key, array $params = []): string
    {
        $value = $this->strings()[$key] ?? $key;
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
        $value = $this->strings()[$fullKey] ?? $fullKey;
        return str_replace('%count%', (string) $count, $value);
    }

    public function currentLocale(): string
    {
        return $_SESSION['locale'] ?? 'sv';
    }

    /**
     * @return array<string,string>
     */
    private function strings(): array
    {
        $locale = $this->currentLocale();
        if (!isset($this->cache[$locale])) {
            $this->cache[$locale] = ($this->stringsLoader)($locale);
        }
        return $this->cache[$locale];
    }
}
