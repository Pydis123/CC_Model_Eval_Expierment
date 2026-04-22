<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
