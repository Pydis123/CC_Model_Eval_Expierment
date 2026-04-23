# Task bank

This directory contains the 8 canonical tasks for the experiment.
Each task has a JSON with metadata and success criteria (consumed by
`runner/bin/cli evaluate`), and a markdown prompt (dispatched to the
subagent).

Tasks are frozen once the experiment begins. Do not edit after a run
has used them.

## Schema

See `schema.json` for the JSON schema. `runner/tests/Integration/TaskBank/TaskBankTest.php`
asserts each task validates.

## List

| ID | Category | Size |
|----|----------|------|
| 001-i18n-status-flik | trivial_i18n | xs |
| 002-crud-ticket-tag | crud_addition | m |
| 003-n-plus-one-fix | query_optimization | s |
| 004-sla-deadline-migration | migration_backfill | m |
| 005-state-service-refactor | refactor | l |
| 006-intermittent-test-bugfix | bugfix_root_cause | l |
| 007-batch-close-rbac | route_rbac | m |
| 008-comment-composer-alpine | frontend_alpine | m |
