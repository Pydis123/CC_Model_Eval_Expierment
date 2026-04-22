<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Service\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashProducesVerifiableString(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret');

        $this->assertNotSame('secret', $hash);
        $this->assertTrue($hasher->verify('secret', $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret');

        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function testHashUsesBcrypt(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret');

        $this->assertStringStartsWith('$2y$', $hash);
    }
}
