# Batch-close API endpoint for admins

Admins need to be able to close multiple tickets at once from an admin
script. Add a JSON API endpoint that accepts a list of ticket IDs and
moves them all to `closed`.

## Acceptance

- `POST /api/admin/tickets/batch-close` with body `{"ticket_ids": [1, 2, 3]}`
- Admin-only: non-admin users get HTTP 403
- Unauthenticated: HTTP 401 with JSON body
- For each ticket, only closes it if its current status allows the
  transition (per the existing state-machine rules); skips others and
  reports them in the response
- Response JSON: `{"closed": [1, 3], "skipped": [2], "skipped_reasons": {"2": "invalid transition from pending"}}`
- Integration tests covering: success case, RBAC (403 for requester role,
  401 without session), invalid transitions reported as skipped, unknown
  ticket IDs reported as skipped
- All existing tests continue to pass
