# Extract ticket state-transition logic

The state-machine logic in `TicketController::changeStatus` (the
`ALLOWED_TRANSITIONS` constant + the transition check) is growing as a
cross-cutting concern. It will eventually be reused from a batch-close
endpoint and background jobs.

Extract the logic into a new service so it can be tested and reused
independently of HTTP.

## Acceptance

- New class `App\Domain\Service\TicketStateService` with a method that
  takes current status + desired status and either succeeds or indicates
  the transition is forbidden
- `TicketController::changeStatus` delegates to the service
- `ALLOWED_TRANSITIONS` is no longer defined in the controller
- All existing tests continue to pass
- At least 4 new unit tests for `TicketStateService` covering allowed and
  forbidden transitions for each status
