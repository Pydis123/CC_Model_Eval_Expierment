# Fix slow /tickets page

Users report that the ticket list loads slowly when the demo DB is
populated. A query-count probe shows `GET /tickets` issues 30+ SQL queries
for a page with only 10 tickets, and it scales linearly with ticket count.

Investigate the cause and fix it so the page issues at most 5 queries
regardless of how many tickets are rendered.

## Acceptance

- `GET /tickets` issues ≤ 5 SQL queries for a page of 50 tickets
- All existing tests still pass
- Add a regression test that demonstrates the fix with 50 tickets

The existing test `TicketControllerTest::testIndexIssuesExpectedQueryCount`
currently asserts the page issues > 20 queries (as a "baseline"). After
your fix, that assertion is no longer meaningful — update the test to
assert the new ceiling, or replace it with a better-named test.
