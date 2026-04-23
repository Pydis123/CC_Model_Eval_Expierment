# Add an "escalated" ticket status

The support team needs a new status for tickets that have been escalated
to second-line support. Add `escalated` as a sixth status in the ticket
system.

## Acceptance

- `tickets.status` ENUM extended with the value `escalated`
- New migration in `database/migrations/` with the next sequential number
- I18n rows seeded for both locales:
  - `tickets.status.escalated` (sv + en)
  - `tickets.filter.escalated` (sv + en)
- The status filter tabs on `/tickets` include "escalated" as a selectable tab
- State machine allows `open → escalated` and `escalated → open`;
  other transitions to/from `escalated` are not allowed
- All existing tests continue to pass
- At least one new test verifying that a ticket can be transitioned to `escalated`
