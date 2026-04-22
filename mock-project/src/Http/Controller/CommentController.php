<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Entity\Comment;
use App\Domain\Entity\User;
use App\Domain\Repository\CommentRepository;
use App\Domain\Repository\TicketRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class CommentController
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly CommentRepository $comments,
        private readonly Twig $twig,
    ) {}

    /**
     * @param array<string,string> $args
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ticketId = (int) ($args['id'] ?? 0);
        $ticket = $this->tickets->findById($ticketId);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'tickets/comments.twig', [
            'ticket' => $ticket,
            'comments' => $this->comments->findByTicket($ticketId),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    /**
     * @param array<string,string> $args
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return $response->withStatus(401);
        }

        $ticketId = (int) ($args['id'] ?? 0);
        if ($this->tickets->findById($ticketId) === null) {
            return $response->withStatus(404);
        }

        $body = $request->getParsedBody();
        $text = is_array($body) ? trim((string) ($body['body'] ?? '')) : '';

        if ($text === '') {
            return $response->withStatus(422);
        }

        $this->comments->insert(new Comment(null, $ticketId, (int) $user->id, $text));

        return $response->withStatus(302)->withHeader('Location', "/tickets/{$ticketId}/comments");
    }
}
