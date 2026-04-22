<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Entity\Category;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Company;
use App\Domain\Entity\Ticket;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\CommentRepository;
use App\Domain\Repository\CompanyRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\PasswordHasher;
use PDO;

final class FixtureLoader
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $cache = [];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Seeds: 2 companies, 1 admin, 2 agents, 2 requesters, 3 categories, 5 tickets, 10 comments.
     *
     * @return array{admin:int,agents:list<int>,requesters:list<int>,companies:list<int>,categories:list<int>,tickets:list<int>}
     */
    public function seedMinimal(): array
    {
        $hasher = new PasswordHasher();
        $companies = new CompanyRepository($this->pdo);
        $users = new UserRepository($this->pdo);
        $categories = new CategoryRepository($this->pdo);
        $tickets = new TicketRepository($this->pdo);
        $comments = new CommentRepository($this->pdo);

        $acme = $companies->insert(new Company(null, 'Acme AB'));
        $globex = $companies->insert(new Company(null, 'Globex AB'));

        $admin = $users->insert(new User(null, 'admin@test.local',
            $hasher->hash('password'), 'Admin', 'admin', null));
        $agent1 = $users->insert(new User(null, 'agent1@test.local',
            $hasher->hash('password'), 'Agent One', 'agent', null));
        $agent2 = $users->insert(new User(null, 'agent2@test.local',
            $hasher->hash('password'), 'Agent Two', 'agent', null));
        $req1 = $users->insert(new User(null, 'req1@test.local',
            $hasher->hash('password'), 'Req One', 'requester', $acme->id));
        $req2 = $users->insert(new User(null, 'req2@test.local',
            $hasher->hash('password'), 'Req Two', 'requester', $globex->id));

        $catTech = $categories->insert(new Category(null, 'Technical', 24));
        $catBilling = $categories->insert(new Category(null, 'Billing', 48));
        $catGeneral = $categories->insert(new Category(null, 'General', 72));

        $t1 = $tickets->insert(new Ticket(null, 'Printer broken', 'It jams',
            'open', $agent1->id, (int) $req1->id, (int) $catTech->id));
        $t2 = $tickets->insert(new Ticket(null, 'Invoice question', 'Line 3',
            'new', null, (int) $req2->id, (int) $catBilling->id));
        $t3 = $tickets->insert(new Ticket(null, 'General question', 'Hello',
            'pending', (int) $agent2->id, (int) $req1->id, (int) $catGeneral->id));
        $t4 = $tickets->insert(new Ticket(null, 'Resolved one', 'Done',
            'resolved', (int) $agent1->id, (int) $req2->id, (int) $catTech->id));
        $t5 = $tickets->insert(new Ticket(null, 'Closed one', 'Archive',
            'closed', (int) $agent2->id, (int) $req1->id, (int) $catGeneral->id));

        foreach ([$t1, $t2, $t3, $t4, $t5] as $idx => $ticket) {
            $comments->insert(new Comment(null, (int) $ticket->id, (int) $agent1->id, "Note A on {$idx}"));
            $comments->insert(new Comment(null, (int) $ticket->id, (int) $req1->id, "Reply B on {$idx}"));
        }

        return [
            'admin' => (int) $admin->id,
            'agents' => [(int) $agent1->id, (int) $agent2->id],
            'requesters' => [(int) $req1->id, (int) $req2->id],
            'companies' => [(int) $acme->id, (int) $globex->id],
            'categories' => [(int) $catTech->id, (int) $catBilling->id, (int) $catGeneral->id],
            'tickets' => [(int) $t1->id, (int) $t2->id, (int) $t3->id, (int) $t4->id, (int) $t5->id],
        ];
    }

    /**
     * Seeds `$n` additional tickets beyond seedMinimal for N+1 query-count tests.
     *
     * @return list<int> IDs of inserted tickets
     */
    public function seedManyTickets(int $n, int $requesterId, int $categoryId, ?int $assigneeId = null): array
    {
        $tickets = new TicketRepository($this->pdo);
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $t = $tickets->insert(new Ticket(
                null,
                "Bulk ticket {$i}",
                "Body {$i}",
                'open',
                $assigneeId,
                $requesterId,
                $categoryId,
            ));
            $ids[] = (int) $t->id;
        }
        return $ids;
    }
}
