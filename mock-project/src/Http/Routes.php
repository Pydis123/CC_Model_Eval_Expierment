<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Repository\UserRepository;
use App\Http\Controller\Admin;
use App\Http\Controller\AuthController;
use App\Http\Controller\CommentController;
use App\Http\Controller\HealthController;
use App\Http\Controller\LocaleController;
use App\Http\Controller\TicketController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SessionMiddleware;
use Slim\App as SlimApp;
use Slim\Routing\RouteCollectorProxy;

final class Routes
{
    public static function register(SlimApp $app): void
    {
        // Middleware stack (LIFO: last added runs outermost).
        // Desired outer→inner: Session → Locale → Csrf → (route-specific Auth/Role).
        $app->add(CsrfMiddleware::class);
        $app->add(LocaleMiddleware::class);
        $app->add(SessionMiddleware::class);

        $c = $app->getContainer();

        // Health (public)
        $app->get('/api/health', [HealthController::class, 'health']);

        // Auth (public)
        $app->get('/login', [AuthController::class, 'showLogin']);
        $app->post('/login', [AuthController::class, 'login']);

        // Locale switch (public)
        $app->get('/locale/{code}', [LocaleController::class, 'set']);

        // Authenticated HTML routes
        $app->group('', function (RouteCollectorProxy $g) {
            $g->get('/', fn($req, $res) => $res->withStatus(302)->withHeader('Location', '/tickets'));

            $g->post('/logout', [AuthController::class, 'logout']);

            $g->get('/tickets', [TicketController::class, 'index']);
            $g->get('/tickets/new', [TicketController::class, 'showNew']);
            $g->post('/tickets', [TicketController::class, 'create']);
            $g->get('/tickets/{id}', [TicketController::class, 'show']);
            $g->post('/tickets/{id}/status', [TicketController::class, 'changeStatus'])
                ->add(new RoleMiddleware(['admin', 'agent']));

            $g->get('/tickets/{id}/comments', [CommentController::class, 'index']);
            $g->post('/tickets/{id}/comments', [CommentController::class, 'create']);
        })->add(new AuthMiddleware($c->get(UserRepository::class)));

        // Admin HTML group
        $app->group('/admin', function (RouteCollectorProxy $g) {
            $g->get('/users', [Admin\UserController::class, 'index']);
            $g->get('/companies', [Admin\CompanyController::class, 'index']);
            $g->get('/categories', [Admin\CategoryController::class, 'index']);
        })
            ->add(new RoleMiddleware(['admin']))
            ->add(new AuthMiddleware($c->get(UserRepository::class)));

        // Admin JSON API group — admin-only, routes added as features are built.
        $app->group('/api/admin', function (RouteCollectorProxy $g) {
        })
            ->add(new RoleMiddleware(['admin']))
            ->add(new AuthMiddleware($c->get(UserRepository::class)));
    }
}
