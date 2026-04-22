<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use App\Http\I18nExtension;
use PHPUnit\Framework\TestCase;

final class I18nExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTReturnsValueForKnownKey(): void
    {
        $_SESSION['locale'] = 'sv';
        $ext = new I18nExtension(fn(string $l) => $l === 'sv' ? ['nav.tickets' => 'Ärenden'] : []);

        $this->assertSame('Ärenden', $ext->t('nav.tickets'));
    }

    public function testTReturnsKeyWhenMissing(): void
    {
        $_SESSION['locale'] = 'sv';
        $ext = new I18nExtension(fn() => []);

        $this->assertSame('nav.missing', $ext->t('nav.missing'));
    }

    public function testTSubstitutesParameters(): void
    {
        $_SESSION['locale'] = 'sv';
        $ext = new I18nExtension(fn() => ['hello' => 'Hej %name%, du har %count% ärenden']);

        $this->assertSame(
            'Hej Anders, du har 3 ärenden',
            $ext->t('hello', ['name' => 'Anders', 'count' => 3])
        );
    }

    public function testTpUsesZeroOneOtherForms(): void
    {
        $_SESSION['locale'] = 'sv';
        $ext = new I18nExtension(fn() => [
            'comments.count.zero' => 'Inga kommentarer',
            'comments.count.one' => '1 kommentar',
            'comments.count.other' => '%count% kommentarer',
        ]);

        $this->assertSame('Inga kommentarer', $ext->tp('comments.count', 0));
        $this->assertSame('1 kommentar', $ext->tp('comments.count', 1));
        $this->assertSame('5 kommentarer', $ext->tp('comments.count', 5));
    }

    public function testTpReturnsKeyWithFormWhenMissing(): void
    {
        $_SESSION['locale'] = 'sv';
        $ext = new I18nExtension(fn() => []);

        $this->assertSame('foo.bar.one', $ext->tp('foo.bar', 1));
    }

    public function testLocaleChangeReloadsStrings(): void
    {
        $loader = fn(string $locale) => match ($locale) {
            'sv' => ['hi' => 'Hej'],
            'en' => ['hi' => 'Hi'],
            default => [],
        };
        $ext = new I18nExtension($loader);

        $_SESSION['locale'] = 'sv';
        $this->assertSame('Hej', $ext->t('hi'));

        $_SESSION['locale'] = 'en';
        $this->assertSame('Hi', $ext->t('hi'));
    }

    public function testCurrentLocaleFallsBackToSv(): void
    {
        $ext = new I18nExtension(fn() => []);

        $this->assertSame('sv', $ext->currentLocale());

        $_SESSION['locale'] = 'en';
        $this->assertSame('en', $ext->currentLocale());
    }
}
