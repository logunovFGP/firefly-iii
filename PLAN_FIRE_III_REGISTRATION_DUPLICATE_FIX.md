# Plan: Firefly III registration duplicate handling

## Scope
Prevent registration 500s when duplicate email/user group creation occurs by making registration listeners and group creation code race-safe and user-facing.

## Items
- [ ] Status: implemented, awaiting user confirmation - Updated firefly-iii/app/Listeners/Security/System/HandlesNewUserRegistration.php to make group title allocation race-safe (firstOrCreate + duplicate-key retry path via user_groups_title_unique, with suffix fallback).
- [ ] Status: implemented, awaiting user confirmation - Updated firefly-iii/app/Http/Controllers/Auth/RegisterController.php to short-circuit duplicate email with validation-style feedback and catch duplicate user insert conflicts.
- [ ] Status: implemented, awaiting user confirmation - Plan updated to track touched files and next verification steps.
- [ ] Status: implemented, awaiting user confirmation - Added `firefly-iii/database/migrations/2026_02_16_000001_add_unique_email_to_users_table.php` to enforce or safely skip `users.email` uniqueness enforcement with duplicate-data guard and idempotent rollback.

## Decision Log
- `AddUniqueEmailToUsersTable` migration uses a duplicate-count pre-check and skips creating `users.email` unique index when legacy duplicates exist. This keeps deployment from failing on preexisting data while still enabling enforcement on clean databases.
