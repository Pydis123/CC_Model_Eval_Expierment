# Add SLA deadline to tickets

The support team needs to see when a ticket must be responded to per its
SLA. Each category already has a `default_sla_hours` value in the
`categories` table. Add an `sla_deadline` column to `tickets` and
backfill it for existing data.

## Acceptance

- New migration (sequential number after existing ones) adding
  `sla_deadline DATETIME NULL` to `tickets`
- The same or a separate migration backfills `sla_deadline` for all
  existing tickets: `created_at + category.default_sla_hours HOUR`
- When new tickets are created via `POST /tickets`, `sla_deadline` is
  set according to the same formula
- `SchemaTest::testTicketsHasExpectedColumns` is updated: the current
  `assertNotContains('sla_deadline', ...)` should now be
  `assertContains('sla_deadline', ...)`
- All existing tests continue to pass
- New tests for the create flow verifying that `sla_deadline` is set correctly
