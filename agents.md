# AGENTS.md

This file prepares `firefly-iii` for AI coding assistants. It complements the existing `agents.md` file (which remains authoritative for PR-footer-specific instructions).

## Read Order
1. `readme.md`
2. `agents.md`
3. `PLAN*.md` in repo root (if present)
4. `plans/archive/` history (if present)
5. `llm.txt`
6. `jira-data-center-llm.txt`

## Repository Snapshot
- Stack: Laravel 12, PHP 8.4+, Composer-managed backend.
- Frontend workspaces: `resources/assets/v1`, `resources/assets/v2`.
- Core backend code: `app/`.
- Shared reusable backend utilities: `app/Support/`.

## Required Engineering Rules
- Prefer reusable components and avoid duplicate logic. If duplicate backend logic appears, centralize it in `app/Support/` (or the nearest existing shared layer).
- Keep data handling decoupled; avoid "god objects" with mixed responsibilities.
- REST API updates must use correct HTTP status codes and predictable error shapes.
- Handle errors gracefully in both backend and frontend paths.
- Do not run destructive git operations. Avoid git changes/reverts unless explicitly requested by the user.

## Plan-Driven Workflow
- Before editing code, create or update a `PLAN*.md` file for the active task.
- Execute remaining unchecked plan items in order.
- Do not mark an item complete until user confirmation. Until then, annotate as:
  - `Status: implemented, awaiting user confirmation`
  - touched file paths
- When a plan section is fully user-confirmed, archive it under `plans/archive/` with:
  - `ARCHIVED_YYYY_MM_DD_SHORT_TITLE.md`

## Verification Commands
Use the repo's existing commands when relevant:
- `composer unit-test`
- `composer integration-test`
- `composer coverage`

Optional environment checks:
- `php artisan --version`
- `php -v`
- `npm -v`

## AI Context Files
- `llm.txt`: fast project context for coding agents.
- `jira-data-center-llm.txt`: issue-tracking and delivery context.
- `claude.md`, `gemini.md`: assistant-specific entry points that delegate to shared guidance.

## MCP Memory
If an MCP Memory server is available, store these known context paths:
- `llm.txt`
- `jira-data-center-llm.txt`
