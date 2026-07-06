# Make ticket status changes atomic with an audit trail

`TicketController::changeStatus` (`mock-project/src/Http/Controller/TicketController.php`)
currently does a single write when a ticket's status changes: it calls
`TicketRepository::updateStatus()` and nothing else. There is no audit
trail — nobody can tell who changed a ticket's status, when, or what it
changed from.

Add an audit trail, and make the whole operation atomic.

1. Add a new `status_history` table (a new migration file under
   `mock-project/database/migrations/`, following the existing
   numeric-prefix convention — the last migration is
   `015_seed_i18n_strings_en.sql`) with columns: `id`, `ticket_id`,
   `from_status`, `to_status`, `changed_by_user_id`, `created_at`. Add a
   matching `App\Domain\Entity\StatusHistory` and
   `App\Domain\Repository\StatusHistoryRepository`, mirroring the existing
   style of `App\Domain\Entity\Comment` / `App\Domain\Repository\CommentRepository`.
2. On every successful status change, in addition to updating
   `tickets.status`:
   - insert one row into `status_history` recording the transition and the
     acting user (`$request->getAttribute('user')`)
   - insert one row into `comments` (via the existing
     `App\Domain\Repository\CommentRepository`), authored by the acting
     user, with a system-generated body that names both statuses, e.g.
     `Status changed from open to pending.`
3. These three writes — ticket status update, status-history insert, audit
   comment insert — must be atomic: if any one of them fails, none of them
   may be visible in the database afterwards. The forbidden-transition
   check (the `ALLOWED_TRANSITIONS` constant and the check against it) is
   unchanged — leave it exactly where it is.

## Acceptance

- `POST /tickets/{id}/status` still behaves exactly as before for valid
  and invalid transitions (same status codes as today), and now
  additionally produces one `status_history` row and one audit comment
  per successful change
- All three writes happen inside a single database transaction; a failure
  partway through leaves the ticket's `status`, and the `status_history`
  and `comments` tables, exactly as they were before the request
- Add a regression test to `TicketControllerTest` that proves the
  rollback: drive a status change acting as a user id that does not exist
  in `users` (e.g. `999999`) — this must eventually fail, because
  `comments.author_user_id` has an existing
  `FOREIGN KEY ... REFERENCES users(id)` constraint (see
  `mock-project/database/migrations/012_add_fk_comments.sql`) — and assert
  afterwards that the ticket's status in the database is unchanged from
  before the call, and that no new row exists in `status_history` or
  `comments` for that ticket
- Add a regression test proving the happy path: a valid status change
  produces exactly one new `status_history` row (correct `from_status` /
  `to_status` / `changed_by_user_id`) and exactly one new audit comment on
  the ticket
- All existing tests continue to pass

## Constraints

- Do not change the allowed-transition rules or where they live
- Do not add a new HTTP endpoint for this — it's the existing
  `POST /tickets/{id}/status` route
- The exact wording of the audit comment is your choice, but it must
  include the old and new status values so a test can assert on it
