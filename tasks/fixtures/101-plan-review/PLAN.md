# Implementation Plan: SLA Escalation Rules

## 1. Background and goal

Every category already carries a `default_sla_hours` column
(`database/migrations/003_create_categories.sql`), but nothing in the
system currently acts on it. Tickets can sit open indefinitely past
their category's SLA window with no signal to agents or admins. This
plan adds SLA escalation tracking: each ticket gets a computed due
date derived from its category's SLA, and tickets that pass that due
date without being resolved are flagged as escalated so agents and
admins can triage them from a dedicated view.

Scope for this iteration:

- A new `sla_escalations` table tracking one row per ticket.
- A backfill for existing tickets.
- A repository + service layer to compute due dates and find overdue
  tickets.
- A small overdue indicator on the existing ticket list, plus a
  dedicated admin list view of overdue/escalated tickets.
- A manual "escalate now" action agents can trigger from a ticket.
- i18n strings for the new UI surfaces, following the existing
  `i18n_strings` pattern.

Out of scope: outbound notifications (email/Slack), automatic
reassignment, and category-level configuration of escalation
thresholds beyond the existing `default_sla_hours` value. Tracked as
follow-ups in section 9.

## 2. Data model

### 2.1 New table: `sla_escalations`

New migration `016_create_sla_escalations.sql`:

```sql
CREATE TABLE sla_escalations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    due_at DATETIME NOT NULL,
    escalated_at DATETIME NULL,
    escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sla_escalations_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

One row per ticket, enforced by the unique key on `ticket_id`, mirroring
how `categories` (`003_create_categories.sql`) uses a single unique key.

### 2.2 Indexes

New migration `017_add_indexes_sla_escalations.sql`, following the
naming convention set by `006_add_indexes_tickets.sql`:

```sql
CREATE INDEX idx_sla_escalations_ticket ON sla_escalations (ticket_id);
```

The `ticket_id` unique key already gives a fast point lookup by ticket.
The escalation sweep (section 4.2) and the admin list view (section
5.3) both filter and sort by `due_at`, so this index is what backs
those code paths.

### 2.3 Foreign key

New migration `018_add_fk_sla_escalations.sql`, matching the pattern in
`012_add_fk_comments.sql`:

```sql
ALTER TABLE sla_escalations
    ADD CONSTRAINT fk_sla_escalations_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON DELETE CASCADE ON UPDATE CASCADE;
```

Cascading delete keeps escalation rows from becoming orphaned if a
ticket is ever hard-deleted, matching `fk_comments_ticket`.

## 3. Backfill

Existing tickets need an `sla_escalations` row before the sweep can
consider them. Runs as a one-off script,
`tools/backfill_sla_escalations.php`, modeled on `tools/migrate.php`'s
bootstrap (load `.env`, build `Config`, connect via
`App\Support\Database::connect`).

### 3.1 Algorithm

```php
$rows = $pdo->query(
    'SELECT t.id, t.created_at, c.sla_hours
     FROM tickets t
     JOIN categories c ON c.id = t.category_id
     WHERE t.id NOT IN (SELECT ticket_id FROM sla_escalations)'
)->fetchAll();

foreach ($rows as $row) {
    $dueAt = (new DateTimeImmutable($row['created_at']))
        ->modify("+{$row['sla_hours']} hours");

    $stmt = $pdo->prepare(
        'INSERT INTO sla_escalations (ticket_id, due_at) VALUES (:ticket, :due)'
    );
    $stmt->execute([':ticket' => $row['id'], ':due' => $dueAt->format('Y-m-d H:i:s')]);
}
```

A straight port of the due-date formula the service layer uses
(section 4.1), so backfilled rows and freshly-created rows compute
`due_at` identically.

### 3.2 Rollout mechanics

The script is idempotent by construction: the `WHERE t.id NOT IN
(SELECT ticket_id FROM sla_escalations)` clause means re-running it
only picks up tickets that don't have a row yet. It's intended to run
once, right after the migrations in section 2, and then be left
alone — the ticket-creation hook (section 5.1) takes over for new
tickets from that point on. Because it's safe to re-run, there's no
need for a maintenance window: it executes against the live database
while the app keeps serving traffic, the same way `tools/migrate.php`
does today. The read (`SELECT ... NOT IN ...`) and the per-row insert
are two separate statements with no surrounding transaction or row
lock, which is fine here since the script only ever adds rows that
don't exist yet.

Batch size: the script processes all pending tickets in one query.
Given current data volumes this completes in well under a second, so
no batching/chunking is implemented for v1.

## 4. Domain and service layer

### 4.1 Entity and repository

New `src/Domain/Entity/SlaEscalation.php`, following the shape of
`src/Domain/Entity/Category.php` (readonly constructor properties,
`fromRow()` factory).

New `src/Domain/Repository/SlaEscalationRepository.php`, matching the
constructor-injected-PDO pattern used by every repository in
`src/Domain/Repository/`:

```php
final class SlaEscalationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(SlaEscalation $escalation): SlaEscalation { /* ... */ }

    public function findByTicketId(int $ticketId): ?SlaEscalation { /* ... */ }

    /**
     * @return list<SlaEscalation>
     */
    public function findOverdue(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sla_escalations
             WHERE due_at <= NOW() AND escalated_at IS NULL
             ORDER BY due_at ASC'
        );
        $stmt->execute();
        return array_map(SlaEscalation::fromRow(...), $stmt->fetchAll());
    }

    public function markEscalated(int $id): void { /* UPDATE ... SET escalated_at = NOW() */ }
}
```

`findOverdue()` is the query the admin list view and the manual
escalate action both reach through the service layer below.

### 4.2 Service

New `src/Domain/Service/SlaEscalationService.php`, following the
pattern in `src/Domain/Service/RecentActivityService.php` (thin service
wrapping repository calls with a bit of orchestration):

```php
final class SlaEscalationService
{
    public function __construct(
        private readonly SlaEscalationRepository $escalations,
        private readonly CategoryRepository $categories,
    ) {}

    public function computeDueAt(Ticket $ticket): DateTimeImmutable
    {
        $category = $this->categories->findById($ticket->categoryId);
        $hours = $category?->defaultSlaHours ?? 24;
        return ($ticket->createdAt ?? new DateTimeImmutable())->modify("+{$hours} hours");
    }

    public function overdueTickets(): array
    {
        return $this->escalations->findOverdue();
    }

    public function escalate(int $ticketId): void
    {
        $escalation = $this->escalations->findByTicketId($ticketId);
        if ($escalation !== null && $escalation->escalatedAt === null) {
            $this->escalations->markEscalated((int) $escalation->id);
        }
    }
}
```

## 5. HTTP layer

### 5.1 Ticket creation hook

`TicketController::create` (`src/Http/Controller/TicketController.php`)
currently inserts the ticket and redirects. After this change, right
after `$this->tickets->insert(...)` returns the saved `Ticket`, it also
calls `$this->escalations->insert(new SlaEscalation(null, (int)
$ticket->id, $this->slaService->computeDueAt($ticket), null, 0))`.
`TicketController` gains `SlaEscalationRepository $escalations` and
`SlaEscalationService $slaService` as constructor dependencies, wired
through the container the same way `CategoryRepository` already is.

### 5.2 Ticket list overdue indicator

`TicketController::index` currently calls `$this->tickets->findAll()`
and builds one row per ticket with its assignee/requester/category
looked up individually (`src/Http/Controller/TicketController.php`).
To show an overdue badge on this page without an extra per-row query,
`TicketRepository::findAll()` is changed to left join `sla_escalations`
and return each result as an associative array of `['ticket' => ...,
'due_at' => ..., 'escalated_at' => ...]` instead of a plain `Ticket`.
The rendering loop in `index()` is updated to read `$row['ticket']`
instead of `$row` directly.

### 5.3 Admin list view

New `src/Http/Controller/Admin/SlaEscalationController.php`, matching
`src/Http/Controller/Admin/CategoryController.php`: constructor takes
`SlaEscalationService`, `TicketRepository`, and `Twig`; `index()`
renders `admin/sla_escalations.twig` with `overdue =>
$this->slaService->overdueTickets()`, plus the usual `csrf_token` and
`user` template variables.

### 5.4 Manual escalate action

Agents need a way to force-escalate a ticket that's about to breach
SLA without waiting for the next sweep. New route, registered in
`src/Http/Routes.php` right after the existing status-change route:

```php
$g->post('/tickets/{id}/status', [TicketController::class, 'changeStatus'])
    ->add(new RoleMiddleware(['admin', 'agent']));

$g->post('/tickets/{id}/escalate', [TicketController::class, 'escalate']);
```

`TicketController::escalate` follows the same shape as `changeStatus`:
look up the ticket by id, 404 if missing, call
`$this->slaService->escalate($id)`, then redirect back to the ticket
page with a 302. This is a state-changing write, so it goes in the
authenticated group alongside the rest of the ticket routes, gated by
`AuthMiddleware` the same as every other route in that group.

### 5.5 Container wiring

`SlaEscalationRepository` and `SlaEscalationService` are registered in
the container definitions the same way `CategoryRepository` is
today — a factory closure resolving `PDO` from the container.
`Admin\SlaEscalationController` is registered the same way as
`Admin\CategoryController`, and the admin group in `Routes.php` gets
one more line: `$g->get('/sla-escalations',
[Admin\SlaEscalationController::class, 'index']);`.

## 6. i18n strings

New migration `019_seed_i18n_strings_sla_escalations.sql`, following
the pattern of `014_seed_i18n_strings_sv.sql` /
`015_seed_i18n_strings_en.sql`:

```sql
INSERT INTO i18n_strings (locale, key_name, value) VALUES
('sv', 'admin.sla_escalations.title', 'SLA-eskalering'),
('sv', 'admin.sla_escalations.header_ticket', 'Ärende'),
('sv', 'admin.sla_escalations.header_due', 'Förfaller'),
('sv', 'admin.categories.header_sla', 'Timmar kvar'),
('sv', 'tickets.show.escalate_button', 'Eskalera nu'),
('en', 'admin.sla_escalations.title', 'SLA Escalation'),
('en', 'admin.sla_escalations.header_ticket', 'Ticket'),
('en', 'admin.sla_escalations.header_due', 'Due'),
('en', 'tickets.show.escalate_button', 'Escalate now');
```

`admin.sla_escalations.*` are new keys for the new admin page.
`tickets.show.escalate_button` is a new key for the button added to
the ticket detail view. `admin.categories.header_sla` is reused here
so the "hours remaining" column on the new escalation list uses the
same label as the existing SLA column on the categories admin page,
keeping the terminology consistent between the two screens.

## 7. Testing plan

### 7.1 Repository and service tests

`tests/Integration/Repository/SlaEscalationRepositoryTest.php`,
mirroring `tests/Integration/Repository/TicketRepositoryTest.php`'s
transaction-per-test setup (`beginTransaction()` in `setUp()`,
`rollBack()` in `tearDown()`): `testInsertAndFindByTicketId`,
`testFindOverdueReturnsOnlyPastDueUnescalated`,
`testMarkEscalatedSetsEscalatedAt`.

`tests/Unit/Service/SlaEscalationServiceTest.php`:
`testComputeDueAtAddsCategorySlaHours` (asserts the returned
`DateTimeImmutable` equals `createdAt + defaultSlaHours` hours for a
fixed category/ticket pair), `testEscalateIsIdempotent` (calling
`escalate()` twice only sets `escalated_at` once).

### 7.2 Controller / route test

`tests/Integration/Http/TicketControllerTest.php` gains:

```php
public function testEscalateEndpointDoesNotError(): void
{
    $response = $this->post("/tickets/{$this->ticketId}/escalate", []);

    $this->assertNotEquals(500, $response->getStatusCode());
}
```

This guards against the escalate action throwing an unhandled
exception (missing route wiring, container resolution failure, etc.)
while keeping the test fast and independent of ticket/category fixture
state, since it doesn't need to assert on the exact resulting status
or on `sla_escalations` row contents.

### 7.3 Admin list view test

`tests/Integration/Http/Admin/SlaEscalationControllerTest.php`,
mirroring `tests/Integration/Http/Admin/CategoryControllerTest.php`:
`testIndexRendersOverdueTickets` seeds one overdue and one not-yet-due
ticket, asserts only the overdue one appears in the rendered response
body.

### 7.4 Smoke test

Extend `tests/Smoke/TicketLifecycleSmokeTest.php` with one additional
step after ticket creation: fetch the ticket detail page and assert
the escalate button is present when the SLA has already lapsed
(simulate by inserting a category with `default_sla_hours = 0`).

## 8. Rollout and rollback

### 8.1 Deployment order

1. Apply migrations 016–019 via `tools/migrate.php` (`Migrator::run()`
   applies them in filename order, same as every prior batch).
2. Run `tools/backfill_sla_escalations.php` once.
3. Deploy the application code from sections 4–6.
4. Verify `/admin/sla-escalations` loads and shows the expected
   overdue tickets.

### 8.2 Rollback

If this needs to be reverted after deploy:

1. Revert the application code (sections 4–6) to the prior release.
2. Run the following cleanup SQL by hand, since the migration runner
   has no down-migration support:

   ```sql
   DELETE FROM i18n_strings WHERE locale = 'sv';
   DROP TABLE sla_escalations;
   ```

3. Remove the `schema_migrations` rows for `016_*` through `019_*` so
   a future re-deploy can re-apply them cleanly.

This restores the schema to its pre-change state and removes the
strings introduced in section 6.

## 9. Follow-ups (out of scope for this iteration)

- Outbound notification (email/Slack) when a ticket escalates.
- A periodic sweep job (cron or queue worker) that calls
  `SlaEscalationService::overdueTickets()` and marks rows escalated on
  a schedule, rather than relying solely on the manual "escalate now"
  button. For v1, escalation state only changes when an agent clicks
  the button or the admin list is viewed.
- Per-category configurable escalation levels (level 1 at 100% of SLA,
  level 2 at 150%, etc.) — v1 only tracks a single `escalation_level`
  column for forward compatibility but never increments it past 0/1.
- Company-level SLA overrides (some companies may contractually get
  faster SLAs than their ticket's category default).

## 10. Risks and mitigations

- **Category with no ticket volume history**: `computeDueAt` falls
  back to a 24-hour default if `categories.findById` somehow returns
  null (shouldn't happen given `fk_tickets_category`, but defensive
  nonetheless).
- **Backfill running twice**: covered by the `NOT IN` guard in section
  3.1; safe to re-run.
- **i18n key collisions across locales**: `uniq_locale_key` is scoped
  to `(locale, key_name)` per `013_create_i18n_strings.sql`, so the
  same `key_name` can exist once per locale; the seed in section 6
  supplies both `sv` and `en` rows for every new key, matching the
  existing pattern where every `sv` key in `014_seed_i18n_strings_sv.sql`
  has a corresponding `en` entry in `015_seed_i18n_strings_en.sql`.
- **Twig template omissions**: `admin/sla_escalations.twig` needs to be
  added alongside the controller; template content isn't detailed here
  since it's a straightforward table render matching
  `admin/categories.twig`'s structure.
