<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Entity\User;
use App\Domain\I18n\I18nLoader;
use App\Domain\Repository\CommentRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Http\Controller\CommentController;
use App\Http\I18nExtension;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class CommentControllerTest extends IntegrationTestCase
{
    public function testIndexListsCommentsForTicket(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = $this->controller();
        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', ''),
            (new ResponseFactory())->createResponse(),
            ['id' => (string) $ids['tickets'][0]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Note A on 0', (string) $response->getBody());
    }

    public function testCreateInsertsCommentAndRedirects(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $user = (new UserRepository($this->pdo))->findById($ids['agents'][0]);

        $controller = $this->controller();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '')
            ->withAttribute('user', $user)
            ->withParsedBody(['body' => 'New reply']);
        $response = $controller->create($request, (new ResponseFactory())->createResponse(), ['id' => (string) $ids['tickets'][0]]);

        $this->assertSame(302, $response->getStatusCode());

        $comments = (new CommentRepository($this->pdo))->findByTicket($ids['tickets'][0]);
        $this->assertStringContainsString('New reply', end($comments)->body);
    }

    public function testCreateWithEmptyBodyReturns422(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $user = (new UserRepository($this->pdo))->findById($ids['agents'][0]);

        $controller = $this->controller();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '')
            ->withAttribute('user', $user)
            ->withParsedBody(['body' => '']);
        $response = $controller->create($request, (new ResponseFactory())->createResponse(), ['id' => (string) $ids['tickets'][0]]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testCommentsViewRendersSwedishLabels(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();

        $_SESSION['locale'] = 'sv';
        $loader = new I18nLoader($this->pdo);
        $twig = Twig::create(dirname(__DIR__, 3) . '/templates');
        $twig->addExtension(new I18nExtension(fn(string $l) => $loader->forLocale($l)));

        $controller = new CommentController(
            new TicketRepository($this->pdo),
            new CommentRepository($this->pdo),
            $twig
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', ''),
            (new ResponseFactory())->createResponse(),
            ['id' => (string) $ids['tickets'][0]]
        );

        $body = (string) $response->getBody();
        $this->assertStringContainsString('Kommentarer', $body);
    }

    private function controller(): CommentController
    {
        return new CommentController(
            new TicketRepository($this->pdo),
            new CommentRepository($this->pdo),
            $this->createTwig()
        );
    }
}
