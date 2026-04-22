<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use App\Http\I18nExtension;
use PHPUnit\Framework\TestCase;

final class I18nExtensionTest extends TestCase
{
    public function testTReturnsValueForKnownKey(): void
    {
        $ext = new I18nExtension([
            'nav.tickets' => 'Ärenden',
        ]);

        $this->assertSame('Ärenden', $ext->t('nav.tickets'));
    }

    public function testTReturnsKeyWhenMissing(): void
    {
        $ext = new I18nExtension([]);

        $this->assertSame('nav.missing', $ext->t('nav.missing'));
    }

    public function testTSubstitutesParameters(): void
    {
        $ext = new I18nExtension([
            'hello' => 'Hej %name%, du har %count% ärenden',
        ]);

        $this->assertSame(
            'Hej Anders, du har 3 ärenden',
            $ext->t('hello', ['name' => 'Anders', 'count' => 3])
        );
    }

    public function testTpUsesZeroOneOtherForms(): void
    {
        $ext = new I18nExtension([
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
        $ext = new I18nExtension([]);

        $this->assertSame('foo.bar.one', $ext->tp('foo.bar', 1));
    }
}
