# PLAN: AI Assistant Repository Preparation

## Scope
- Prepare this repository for AI coding assistants by adding repository-local guidance and context files that are safe, documentation-only changes.
- Keep existing runtime behavior unchanged.

## Constraints
- Follow repository guidance in `agents.md` and user-provided AGENTS instructions.
- Do not use destructive git operations or create commits.
- Prefer shared, reusable guidance instead of duplicating conflicting instructions.

## Assumptions
- "Prepare repository for AI coding assistant" means adding maintainable onboarding/context documents used by common assistants.
- Current repository has no existing `PLAN*.md` workflow files or archived plans.

## Decision Log
- Chosen approach: create a small root-level assistant context pack (`AGENTS.md`, `llm.txt`, `jira-data-center-llm.txt`, `claude.md`, `gemini.md`) because it is low-risk and directly improves assistant startup quality without touching application code.
- Added `plans/` and `plans/archive/` README scaffolding so required archive workflow has a ready, explicit location.
- MCP Memory is not configured in this runtime (`list_mcp_resources` returned no servers/resources), so llm-path persistence could not be executed here; paths are documented in `AGENTS.md`.

## Checklist
- [ ] Create canonical `AGENTS.md` with repo-specific coding/validation workflow and explicit links to existing local rules.
  Status: implemented, awaiting user confirmation
  Touched files: `AGENTS.md`
- [ ] Create `llm.txt` repository context summary for fast AI bootstrapping.
  Status: implemented, awaiting user confirmation
  Touched files: `llm.txt`
- [ ] Create `jira-data-center-llm.txt` with task-tracking and integration context constraints for this repo.
  Status: implemented, awaiting user confirmation
  Touched files: `jira-data-center-llm.txt`
- [ ] Create `claude.md` and `gemini.md` as thin wrappers pointing to shared assistant instructions.
  Status: implemented, awaiting user confirmation
  Touched files: `claude.md`, `gemini.md`
- [ ] Create `plans/README.md` and `plans/archive/README.md` scaffolding for plan archival workflow.
  Status: implemented, awaiting user confirmation
  Touched files: `plans/README.md`, `plans/archive/README.md`
- [ ] Verify the new files for consistency and discoverability with local shell checks.
  Status: implemented, awaiting user confirmation
  Touched files: `AGENTS.md`, `llm.txt`, `jira-data-center-llm.txt`, `claude.md`, `gemini.md`, `plans/README.md`, `plans/archive/README.md`

## Verification Log
- 2026-02-10: Verified file presence with `Get-ChildItem` for all new assistant-prep files.
- 2026-02-10: Verified cross-file references with `rg -n "AGENTS\\.md|llm\\.txt|jira-data-center-llm\\.txt"`.
- 2026-02-10: Verified plan-archive scaffolding references with `rg -n "plans/archive|ARCHIVED_YYYY_MM_DD_SHORT_TITLE"`.
- 2026-02-10: Attempted `composer unit-test`; blocked because `composer` is not installed in this environment.
- 2026-02-10: Checked local fallback test binary; `vendor/bin/phpunit` not present.
