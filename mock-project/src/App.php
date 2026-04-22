<?php

declare(strict_types=1);

namespace App;

use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\CommentRepository;
use App\Domain\Repository\CompanyRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\AuthService;
use App\Domain\Service\PasswordHasher;
use App\Support\Database;
use DI\Container;
use PDO;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

final class App
{
    public static function create(?Container $container = null, ?PDO $pdo = null): SlimApp
    {
        $container ??= self::buildContainer($pdo);

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $twig = $container->get(Twig::class);
        $app->add(TwigMiddleware::create($app, $twig));

        return $app;
    }

    public static function buildContainer(?PDO $pdo = null): Container
    {
        $container = new Container();

        $container->set(Config::class, fn() => Config::fromArray($_ENV));

        $container->set(PDO::class, function (Container $c) use ($pdo) {
            if ($pdo !== null) {
                return $pdo;
            }
            return Database::connect($c->get(Config::class));
        });

        $container->set(Twig::class, function () {
            return Twig::create(
                dirname(__DIR__) . '/templates',
                ['cache' => false, 'debug' => true, 'auto_reload' => true]
            );
        });

        $container->set(PasswordHasher::class, fn() => new PasswordHasher());
        $container->set(UserRepository::class, fn(Container $c) => new UserRepository($c->get(PDO::class)));
        $container->set(CompanyRepository::class, fn(Container $c) => new CompanyRepository($c->get(PDO::class)));
        $container->set(CategoryRepository::class, fn(Container $c) => new CategoryRepository($c->get(PDO::class)));
        $container->set(TicketRepository::class, fn(Container $c) => new TicketRepository($c->get(PDO::class)));
        $container->set(CommentRepository::class, fn(Container $c) => new CommentRepository($c->get(PDO::class)));
        $container->set(AuthService::class, fn(Container $c) => new AuthService(
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class)
        ));

        return $container;
    }
}
