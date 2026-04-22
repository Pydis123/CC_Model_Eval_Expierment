<?php

declare(strict_types=1);

namespace App\Tests\Integration\I18n;

use App\Domain\I18n\I18nLoader;
use App\Tests\Support\IntegrationTestCase;

final class I18nLoaderTest extends IntegrationTestCase
{
    public function testReturnsStringsForLocale(): void
    {
        $this->pdo->exec(
            "INSERT INTO i18n_strings (locale, key_name, value) VALUES
             ('sv', '__test__.nav.tickets', 'Ärenden'),
             ('sv', '__test__.nav.logout', 'Logga ut'),
             ('en', '__test__.nav.tickets', 'Tickets')"
        );

        $loader = new I18nLoader($this->pdo);
        $sv = $loader->forLocale('sv');

        $this->assertSame('Ärenden', $sv['__test__.nav.tickets']);
        $this->assertSame('Logga ut', $sv['__test__.nav.logout']);
        $this->assertArrayNotHasKey('en.__test__.nav.tickets', $sv);
    }

    public function testReturnsEmptyArrayForUnknownLocale(): void
    {
        $loader = new I18nLoader($this->pdo);

        $this->assertSame([], $loader->forLocale('de'));
    }

    public function testDoesNotLeakBetweenLocales(): void
    {
        $this->pdo->exec(
            "INSERT INTO i18n_strings (locale, key_name, value) VALUES
             ('sv', '__test__.shared.key', 'Svenska'),
             ('en', '__test__.shared.key', 'English')"
        );

        $loader = new I18nLoader($this->pdo);

        $this->assertSame('Svenska', $loader->forLocale('sv')['__test__.shared.key']);
        $this->assertSame('English', $loader->forLocale('en')['__test__.shared.key']);
    }
}
