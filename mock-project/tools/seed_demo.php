<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
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
use App\Support\Database;

$rootDir = dirname(__DIR__);
if (is_file($rootDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($rootDir)->load();
}

$config = Config::fromArray($_ENV);
$pdo = Database::connect($config);

// Guard: only seed if empty (avoid duplicate data on re-runs)
$existingUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($existingUsers > 0) {
    echo "Users table already populated ({$existingUsers} rows). Skipping seed.\n";
    exit(0);
}

$hasher = new PasswordHasher();
$companies = new CompanyRepository($pdo);
$users = new UserRepository($pdo);
$categories = new CategoryRepository($pdo);
$tickets = new TicketRepository($pdo);
$comments = new CommentRepository($pdo);

echo "Seeding companies...\n";
$companyList = [];
foreach (['Acme AB', 'Globex AB', 'Initech AB'] as $name) {
    $companyList[] = $companies->insert(new Company(null, $name));
}

echo "Seeding users...\n";
$admin = $users->insert(new User(null, 'admin@demo.local',
    $hasher->hash('password'), 'Demo Admin', 'admin', null));

$agents = [];
for ($i = 1; $i <= 3; $i++) {
    $agents[] = $users->insert(new User(null, "agent{$i}@demo.local",
        $hasher->hash('password'), "Agent {$i}", 'agent', null));
}

$requesters = [];
for ($i = 1; $i <= 10; $i++) {
    $companyId = $companyList[$i % 3]->id;
    $requesters[] = $users->insert(new User(null, "requester{$i}@demo.local",
        $hasher->hash('password'), "Requester {$i}", 'requester', $companyId));
}

echo "Seeding categories...\n";
$catList = [];
foreach ([
    ['Technical', 24],
    ['Billing', 48],
    ['General', 72],
    ['Onboarding', 96],
    ['Feature request', 168],
] as [$name, $sla]) {
    $catList[] = $categories->insert(new Category(null, $name, $sla));
}

echo "Seeding 50 tickets...\n";
$statuses = ['new', 'open', 'pending', 'resolved', 'closed'];
$ticketList = [];
for ($i = 1; $i <= 50; $i++) {
    $ticketList[] = $tickets->insert(new Ticket(
        null,
        "Demo ticket {$i}",
        "Demo description for ticket number {$i}. More detail follows.",
        $statuses[$i % 5],
        $i % 3 === 0 ? null : (int) $agents[$i % 3]->id,
        (int) $requesters[$i % 10]->id,
        (int) $catList[$i % 5]->id,
    ));
}

echo "Seeding comments...\n";
foreach ($ticketList as $idx => $ticket) {
    $numComments = ($idx % 3) + 1;
    for ($j = 1; $j <= $numComments; $j++) {
        $authorId = $j % 2 === 0
            ? (int) $agents[$j % 3]->id
            : (int) $requesters[($idx + $j) % 10]->id;
        $comments->insert(new Comment(null, (int) $ticket->id, $authorId,
            "Demo comment {$j} on ticket {$idx}"));
    }
}

$totalComments = (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
echo "Seed complete: 3 companies, 14 users, 5 categories, 50 tickets, {$totalComments} comments.\n";
