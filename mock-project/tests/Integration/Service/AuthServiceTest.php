<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\AuthService;
use App\Domain\Service\PasswordHasher;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private PDO $pdo;
    private AuthService $auth;
    private UserRepository $users;
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $this->users = new UserRepository($this->pdo);
        $this->hasher = new PasswordHasher();
        $this->auth = new AuthService($this->users, $this->hasher);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testAuthenticateReturnsUserOnValidCredentials(): void
    {
        $this->users->insert(new User(
            null, 'user@test.local', $this->hasher->hash('secret'), 'User', 'agent', null
        ));

        $user = $this->auth->authenticate('user@test.local', 'secret');

        $this->assertNotNull($user);
        $this->assertSame('user@test.local', $user->email);
    }

    public function testAuthenticateReturnsNullForWrongPassword(): void
    {
        $this->users->insert(new User(
            null, 'user@test.local', $this->hasher->hash('secret'), 'User', 'agent', null
        ));

        $this->assertNull($this->auth->authenticate('user@test.local', 'wrong'));
    }

    public function testAuthenticateReturnsNullForUnknownEmail(): void
    {
        $this->assertNull($this->auth->authenticate('nobody@test.local', 'secret'));
    }
}
