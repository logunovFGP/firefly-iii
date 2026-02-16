# PLAN: Docker Local Run Setup

## Scope
- Pull the official Firefly III Docker compose files into this repository.
- Add override configuration so the app container uses local repository code changes.
- Document whether custom image builds are required for this workflow.

## Constraints
- Keep changes non-destructive and local-only.
- Reuse official upstream Docker compose definitions as source of truth.
- Do not introduce git operations.

## Assumptions
- "Here" means repository root: `G:\REPOS\firefly-iii`.
- User wants a localhost-ready Docker setup that reflects local source edits.

## Decision Log
- Use official upstream compose (`firefly-iii/docker`) plus `docker-compose.override.yml` bind mount (`.:/var/www/html`) to avoid maintaining a forked base compose file.
- Keep upstream image reference (`fireflyiii/core:latest`) and do not add local image build unless explicitly requested.

## Checklist
- [ ] Pull official `docker-compose.yml` and `database.env` into repo as `docker-compose.yml` and `.db.env`.
  Status: implemented, awaiting user confirmation
  Touched files: `docker-compose.yml`, `.db.env`
- [ ] Add `docker-compose.override.yml` to mount local repository source code into container while preserving upload volume.
  Status: implemented, awaiting user confirmation
  Touched files: `docker-compose.override.yml`
- [ ] Add `DOCKER_LOCAL.md` with run instructions and explicit note on when image build is or is not needed.
  Status: implemented, awaiting user confirmation
  Touched files: `DOCKER_LOCAL.md`
- [ ] Verify compose configuration parses successfully.
  Status: implemented, awaiting user confirmation
  Touched files: `.env`, `PLAN_DOCKER_LOCAL_DEV.md`

## User Feedback (2026-02-10)
- Initial Docker setup was rejected due to runtime failure at `/register` (HTTP 500) and missing explicit preferred host port mapping (`localhost:9999`).
- Additional feedback: clicking sidebar `Automation` redirected to `http://localhost:9999/#` instead of opening submenu.

## Follow-up Checklist
- [ ] Fix `/register` 500 by ensuring required frontend build artifacts exist for V2 layout (Vite manifest and assets).
  Status: implemented, awaiting user confirmation
  Touched files: `public/build/manifest.json`
- [ ] Change host port mapping from `80:8080` to explicit `9999:8080`.
  Status: implemented, awaiting user confirmation
  Touched files: `docker-compose.yml`
- [ ] Re-verify runtime on `http://localhost:9999/register` and capture container log evidence.
  Status: implemented, awaiting user confirmation
  Touched files: `PLAN_DOCKER_LOCAL_DEV.md`, `DOCKER_LOCAL.md`
- [ ] Fix sidebar `Automation` behavior by ensuring V1 JS bundle exists in bind-mounted repo.
  Status: implemented, awaiting user confirmation
  Touched files: `public/v1/js/app.js`, `DOCKER_LOCAL.md`

## Verification Log
- 2026-02-10: `docker compose config` initially failed because `.env` did not exist.
- 2026-02-10: Created `.env` from `.env.example` to satisfy compose `env_file`.
- 2026-02-10: `docker compose config` succeeded and resolved services (`app`, `db`, `cron`).
- 2026-02-10: `docker compose up -d --pull=always` failed because local Docker engine pipe was unavailable (`//./pipe/dockerDesktopLinuxEngine`), indicating Docker Desktop engine is not running.
- 2026-02-10: `docker info` confirms Docker CLI exists but server/daemon is unreachable in current session.
- 2026-02-10: Started `com.docker.service` and launched Docker Desktop; Docker engine became reachable.
- 2026-02-10: `docker compose up -d` completed and containers started.
- 2026-02-10: With local source bind mount enabled, app initially failed because local `vendor/` dependencies were missing (`Illuminate\Foundation\Application` not found).
- 2026-02-10: Ran `docker compose exec app composer install --no-dev --no-interaction --prefer-dist`; dependencies installed into bind-mounted repo.
- 2026-02-10: Verified app runtime with `docker compose logs --tail=20 app` and `curl -I http://localhost` (HTTP 302 to `/login`).
- 2026-02-10: Identified `/register` failure root cause from logs: `Vite manifest not found at /var/www/html/public/build/manifest.json`.
- 2026-02-10: Built V2 assets via `npm --workspace resources/assets/v2 run build`, generating `public/build/manifest.json`.
- 2026-02-10: Updated compose port mapping to `9999:8080` and recreated `app` container.
- 2026-02-10: Re-validated with `curl -I http://localhost:9999/register` => `HTTP/1.1 200 OK`.
- 2026-02-10: Verified running services with `docker compose ps` (`app` healthy on `0.0.0.0:9999->8080`).
- 2026-02-10: Diagnosed `Automation` click issue: `public/v1/js/app.js` was missing in bind-mounted source, so treeview menu handlers were unavailable.
- 2026-02-10: Built V1 assets with `npm --workspace resources/assets/v1 run production`; `public/v1/js/app.js` now exists and is served (`HTTP 200`).

---

# PLAN: Local Plugin Source Integration (2026-02-12)

## Scope
- Wire locally cloned plugin repositories into the existing Docker Compose setup.
- Ensure Firefly III core + data-importer + AI categorizer can run from local source trees for iterative development.
- Document required local env variables and startup/verification commands.

## Constraints
- Keep upstream `docker-compose.yml` as base source of truth.
- Keep plugin integration in local override/docs files to minimize merge conflicts with upstream.
- Avoid destructive git operations.

## Assumptions
- Local directory layout:
  - `G:\REPOS\firefly\firefly-iii`
  - `G:\REPOS\firefly\data-importer`
  - `G:\REPOS\firefly\firefly-iii-ai-categorize`
- User wants to edit local source and re-run containers without pulling prebuilt plugin images only.

## Decision Log
- Add plugin services to `docker-compose.override.yml` instead of mutating the upstream-style base compose file.
- For `data-importer`, use official runtime image plus bind-mounted local source because the repository does not include a Dockerfile.
- For `firefly-iii-ai-categorize`, use local Docker build context and bind mount source with a dedicated `node_modules` volume for stable container runtime.
- Keep importer frontend asset build as an explicit local bootstrap step (in docs) rather than embedding build logic into container startup, to avoid slower every-start behavior and keep service startup deterministic.

## Checklist
- [ ] Add local plugin service wiring to `docker-compose.override.yml` (`importer`, `categorizer`) with network/ports/depends_on and source bind mounts.
  Status: implemented, awaiting user confirmation
  Touched files: `docker-compose.override.yml`
- [ ] Add a dedicated importer env template for local compose (`.importer.env.example`) and keep runtime secrets out of tracked docs.
  Status: implemented, awaiting user confirmation
  Touched files: `.importer.env.example`, `.importer.env`, `.gitignore`
- [ ] Extend `DOCKER_LOCAL.md` with plugin-specific bootstrap, URLs, and local compile/update workflow.
  Status: implemented, awaiting user confirmation
  Touched files: `DOCKER_LOCAL.md`
- [ ] Verify merged compose config renders successfully with plugin services.
  Status: implemented, awaiting user confirmation
  Touched files: `PLAN_DOCKER_LOCAL_DEV.md`
- [ ] Fix importer local-source runtime 500 on `/new-import/<flow>` by generating importer Vite assets and documenting that bootstrap step.
  Status: implemented, awaiting user confirmation
  Touched files: `DOCKER_LOCAL.md`, `../data-importer/public/build/manifest.json`

## Verification Log (2026-02-12)
- 2026-02-12: Updated `docker-compose.override.yml` with local plugin services: `importer` (bind mount `../data-importer`) and `categorizer` (local build context `../firefly-iii-ai-categorize` + bind mount).
- 2026-02-12: Added `.importer.env.example`, copied to local `.importer.env`, and ignored `.importer.env` in `.gitignore` to avoid committing secrets.
- 2026-02-12: `docker compose config` succeeded with merged services: `app`, `db`, `cron`, `importer`, `categorizer`.
- 2026-02-12: First `docker compose up -d --build` failed due Docker engine not running (`//./pipe/dockerDesktopLinuxEngine`).
- 2026-02-12: Started Docker service/desktop and reran `docker compose up -d --build`; containers were created and started successfully.
- 2026-02-12: Verified runtime: `docker compose ps` shows `app` healthy (`9999`), `importer` healthy (`9998`), `categorizer` up (`9997`).
- 2026-02-12: HTTP probes after startup: `http://localhost:9999` => `302`, `http://localhost:9999/login` => `302`, `http://localhost:9998` => `302`, `http://localhost:9997` => `200`.
- 2026-02-13: Verified local host has `PHP 8.4.16` and `Composer 2.9.5`; enabled required `ext-intl` and `ext-fileinfo` in `C:\php\php.ini` for local Composer usage.
- 2026-02-13: Ran `docker compose exec app composer install --no-dev --no-interaction --prefer-dist` and `docker compose exec importer composer install --no-dev --no-interaction --prefer-dist`; both completed successfully.
- 2026-02-13: Diagnosed importer `/new-import/basisbank` and `/new-import/tbank` 500 errors as missing Vite manifest in bind-mounted `../data-importer` source (`../data-importer/public/build/manifest.json`).
- 2026-02-13: Built importer assets locally via `npm install` and `npm --workspace resources/js/v2 run build` in `../data-importer`.
- 2026-02-13: Re-verified importer flow pages: `http://localhost:9998/new-import/basisbank` => `200`, `http://localhost:9998/new-import/tbank` => `200`; token input fields are present.
