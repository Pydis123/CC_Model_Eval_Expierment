# Add comment activity to the ticket list without an N+1

`GET /tickets` (`TicketController::index`, in
`mock-project/src/Http/Controller/TicketController.php`) renders every
ticket's requester, assignee, and category by looking each one up
individually inside the loop — one query per relation per ticket. Support
wants two more columns on that page: how many comments a ticket has, and
when the most recent comment was posted (or blank, if it has none).

Naively bolting this on (one query per ticket for the count, one more for
the latest comment) makes an already slow page slower. Fix both problems
together.

## Acceptance

- The `/tickets` table (`mock-project/templates/tickets/index.twig`) gains
  two columns: comment count, and last-activity timestamp (the most
  recent comment's `created_at` for that ticket, or blank if it has no
  comments)
- `GET /tickets` issues at most 5 SQL queries in total, no matter how many
  tickets are on the page — no per-ticket queries for requester, assignee,
  category, comment count, or last activity. Everything must be fetched in
  a constant number of batched queries.
- All existing tests continue to pass

The existing test `TicketControllerTest::testIndexIssuesExpectedQueryCount`
currently asserts the page issues more than 20 queries (documented there
as the N+1 "baseline"). Update it — or replace it with a better-named
test — to:

- seed a handful of tickets with varying comment counts, including at
  least one ticket with zero comments
- assert the query count for `GET /tickets` stays at or below 5 for that
  page
- assert the rendered page shows the correct comment count and
  last-activity value for at least one ticket that has comments and one
  that doesn't

## Notes

- `App\Domain\Repository\CommentRepository` currently only exposes
  `findByTicket(int $ticketId)`, which is itself a per-ticket query — don't
  call it once per row. Add a method that returns comment counts and
  last-activity timestamps for a batch of ticket ids in a single query
  (e.g. one `GROUP BY ticket_id` query).
- `App\Domain\Repository\UserRepository` and
  `App\Domain\Repository\CategoryRepository` currently only expose
  `findById()` — the same one-per-row problem applies to the existing
  requester/assignee/category lookups on this page. Batch those too (e.g.
  `WHERE id IN (...)`).
