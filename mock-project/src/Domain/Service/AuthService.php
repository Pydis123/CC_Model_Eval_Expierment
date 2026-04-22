<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {}

    public function authenticate(string $email, string $plainPassword): ?User
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return null;
        }
        if (!$this->hasher->verify($plainPassword, $user->passwordHash)) {
            return null;
        }
        return $user;
    }
}
