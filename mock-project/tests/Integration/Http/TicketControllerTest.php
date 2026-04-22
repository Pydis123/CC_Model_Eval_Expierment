<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Entity\Category;
use App\Domain\Entity\Ticket;
use App\Domain\Entity\User;
use App\Domain\I18n\I18nLoader;
use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Http\Controller\TicketController;
use App\Http\I18nExtension;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class TicketControllerTest extends IntegrationTestCase
{
    public function testIndexRendersAllTickets(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = $this->controller();
        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/tickets'),
            (new ResponseFactory())->createResponse()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Printer broken', (string) $response->getBody());
    }

    public function testShowRendersSingleTicket(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = $this->controller();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets/' . $ids['tickets'][0]);
        $response = $controller->show($request, (new ResponseFactory())->createResponse(), ['id' => (string) $ids['tickets'][0]]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateInsertsNewTicketAndRedirects(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = $this->controller();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/tickets')
            ->withAttribute('user', $this->userById($ids['requesters'][0]))
            ->withParsedBody([
                'subject' => 'New issue',
                'description' => 'Help',
                'category_id' => (string) $ids['categories'][0],
            ]);
        $response = $controller->create($request, (new ResponseFactory())->createResponse());

        $this->assertSame(302, $response->getStatusCode());
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tickets WHERE subject = "New issue"')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testChangeStatusFromOpenToPendingIsAllowed(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        // ticket 0 is seeded with status 'open'
        $ticketId = $ids['tickets'][0];

        $controller = $this->controller();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', "/tickets/{$ticketId}/status")
            ->withAttribute('user', $this->userById($ids['agents'][0]))
            ->withParsedBody(['status' => 'pending']);
        $response = $controller->changeStatus($request, (new ResponseFactory())->createResponse(), ['id' => (string) $ticketId]);

        $this->assertSame(302, $response->getStatusCode());
        $status = $this->pdo->query("SELECT status FROM tickets WHERE id = {$ticketId}")->fetchColumn();
        $this->assertSame('pending', $status);
    }

    public function testChangeStatusForbiddenTransitionReturns422(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $ticketId = $ids['tickets'][0]; // 'open'

        $controller = $this->controller();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', "/tickets/{$ticketId}/status")
            ->withAttribute('user', $this->userById($ids['agents'][0]))
            ->withParsedBody(['status' => 'closed']);  // open → closed not allowed
        $response = $controller->changeStatus($request, (new ResponseFactory())->createResponse(), ['id' => (string) $ticketId]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testIndexIssuesExpectedQueryCount(): void
    {
        // Baseline query count: index() issues ~1 + 3N queries today because
        // each ticket's related user/category is fetched individually.
        $fixtures = new FixtureLoader($this->pdo);
        $ids = $fixtures->seedMinimal();
        $fixtures->seedManyTickets(5, $ids['requesters'][0], $ids['categories'][0], $ids['agents'][0]);

        $this->pdo->exec('SET SESSION long_query_time = 0');
        $before = (int) $this->pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];

        $controller = $this->controller();
        $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/tickets'),
            (new ResponseFactory())->createResponse()
        );

        $after = (int) $this->pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];
        $queries = $after - $before - 1; // subtract the closing STATUS query itself

        // 10 tickets × 3 lookups + 1 findAll ≈ 31 queries. Guard: must be > 20 to prove N+1.
        $this->assertGreaterThan(20, $queries, 'Expected natural N+1; got only ' . $queries . ' queries');
    }

    public function testIndexRendersSwedishLabels(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $twig = Twig::create(dirname(__DIR__, 3) . '/templates');
        $strings = (new I18nLoader($this->pdo))->forLocale('sv');
        $twig->addExtension(new I18nExtension($strings));
        $twig->getEnvironment()->addGlobal('locale', 'sv');

        $controller = new TicketController(
            new TicketRepository($this->pdo),
            new UserRepository($this->pdo),
            new CategoryRepository($this->pdo),
            $twig
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/tickets'),
            (new ResponseFactory())->createResponse()
        );

        $body = (string) $response->getBody();
        $this->assertStringContainsString('Ärenden', $body);
        $this->assertStringContainsString('Ämne', $body);
        $this->assertStringContainsString('Status', $body);
    }

    private function controller(): TicketController
    {
        return new TicketController(
            new TicketRepository($this->pdo),
            new UserRepository($this->pdo),
            new CategoryRepository($this->pdo),
            $this->createTwig()
        );
    }

    private function userById(int $id): User
    {
        return (new UserRepository($this->pdo))->findById($id);
    }
}
