<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Entity\Ticket;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class TicketController
{
    private const ALLOWED_TRANSITIONS = [
        'new' => ['open'],
        'open' => ['pending', 'resolved'],
        'pending' => ['open'],
        'resolved' => ['closed', 'open'],
        'closed' => ['open'],
    ];

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly UserRepository $users,
        private readonly CategoryRepository $categories,
        private readonly Twig $twig,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tickets = $this->tickets->findAll();

        $rows = [];
        foreach ($tickets as $ticket) {
            $rows[] = [
                'ticket' => $ticket,
                'assignee' => $ticket->assigneeUserId ? $this->users->findById($ticket->assigneeUserId) : null,
                'requester' => $this->users->findById($ticket->requesterUserId),
                'category' => $this->categories->findById($ticket->categoryId),
            ];
        }

        return $this->twig->render($response, 'tickets/index.twig', [
            'rows' => $rows,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    /**
     * @param array<string,string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $ticket = $this->tickets->findById($id);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'tickets/show.twig', [
            'ticket' => $ticket,
            'assignee' => $ticket->assigneeUserId ? $this->users->findById($ticket->assigneeUserId) : null,
            'requester' => $this->users->findById($ticket->requesterUserId),
            'category' => $this->categories->findById($ticket->categoryId),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function showNew(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'tickets/new.twig', [
            'categories' => $this->categories->findAll(),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return $response->withStatus(401);
        }

        $body = $request->getParsedBody();
        $subject = is_array($body) ? (string) ($body['subject'] ?? '') : '';
        $description = is_array($body) ? (string) ($body['description'] ?? '') : '';
        $categoryId = is_array($body) ? (int) ($body['category_id'] ?? 0) : 0;

        if ($subject === '' || $description === '' || $categoryId <= 0) {
            return $response->withStatus(422);
        }

        $this->tickets->insert(new Ticket(
            null, $subject, $description, 'new', null, (int) $user->id, $categoryId
        ));

        return $response->withStatus(302)->withHeader('Location', '/tickets');
    }

    /**
     * State-transition logic lives inline here. Allowed transitions are defined in
     * ALLOWED_TRANSITIONS above and validated directly within this method.
     *
     * @param array<string,string> $args
     */
    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $ticket = $this->tickets->findById($id);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        $body = $request->getParsedBody();
        $desired = is_array($body) ? (string) ($body['status'] ?? '') : '';

        $allowed = self::ALLOWED_TRANSITIONS[$ticket->status] ?? [];
        if (!in_array($desired, $allowed, true)) {
            return $response->withStatus(422);
        }

        $this->tickets->updateStatus($id, $desired);

        return $response->withStatus(302)->withHeader('Location', "/tickets/{$id}");
    }
}
