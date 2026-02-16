# Firefly III Docker Local Run (Using This Repo)

This setup uses the official Firefly III Docker compose file plus an override that bind-mounts this repository into the app container, and also wires local plugin repositories (`data-importer`, `firefly-iii-ai-categorize`).

## Files
- `docker-compose.yml` (pulled from `firefly-iii/docker`)
- `.db.env` (pulled from `firefly-iii/docker`)
- `docker-compose.override.yml` (local bind-mount override)
- `.importer.env` (local data-importer runtime configuration)

## 1) Prepare env file
Create `.env` from `.env.example`:

```powershell
Copy-Item .\.env.example .\.env
```

Set at least these values in `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=firefly
DB_USERNAME=firefly
DB_PASSWORD=secret_firefly_password
```

In `.db.env`, ensure the password matches:

```env
MYSQL_USER=firefly
MYSQL_PASSWORD=secret_firefly_password
MYSQL_DATABASE=firefly
```

Add AI categorizer credentials to `.env`:

```env
CATEGORIZER_FIREFLY_PERSONAL_TOKEN=replace_with_firefly_pat
CATEGORIZER_OPENAI_API_KEY=replace_with_openai_key
CATEGORIZER_ENABLE_UI=true
CATEGORIZER_FIREFLY_TAG=AI categorized
```

Create importer env file:

```powershell
Copy-Item .\.importer.env.example .\.importer.env
```

Then set in `.importer.env`:

```env
FIREFLY_III_URL=http://app:8080
VANITY_URL=http://localhost:9999
FIREFLY_III_ACCESS_TOKEN=replace_with_firefly_pat
```

## 2) Start containers

```powershell
docker compose up -d --build
```

Open:
- `http://localhost:9999`
- `http://localhost:9998` (data importer)
- `http://localhost:9997` (AI categorizer UI, if enabled)

## 3) First run with local source bind-mount
Because `docker-compose.override.yml` mounts local source code into containers, ensure local dependencies/build artifacts exist:

```powershell
docker compose exec app composer install --no-dev --no-interaction --prefer-dist
docker compose exec importer composer install --no-dev --no-interaction --prefer-dist
npm install
npm --workspace resources/assets/v1 run production
npm --workspace resources/assets/v2 run build
```

This generates:
- `public/v1/js/app.js` and related V1 assets (required for sidebar/treeview behavior such as `Automation` menu expansion).
- `public/build/manifest.json` and V2 assets (required by V2-rendered views such as `/register`).

Because data-importer is also bind-mounted from local source, build its V2 assets as well:

```powershell
Push-Location ..\data-importer
npm install
npm --workspace resources/js/v2 run build
Pop-Location
```

This generates:
- `../data-importer/public/build/manifest.json` and importer V2 assets (required by importer views like `/new-import/<flow>`).

For AI categorizer source changes:
- service is built from `../firefly-iii-ai-categorize` and bind-mounted into `/app`.
- restart the service after code changes:

```powershell
docker compose restart categorizer
```

## Do I Need To Build An Image?
- For normal usage and local code iteration with this setup: **No**.
- `docker-compose.yml` uses prebuilt image `fireflyiii/core:latest`.
- Because `docker-compose.override.yml` bind-mounts this repo into `/var/www/html`, local code changes are used directly by the running container.
- `data-importer` uses `fireflyiii/data-importer:latest` plus bind-mounted local source.
- `categorizer` is built from local source (`../firefly-iii-ai-categorize/Dockerfile`) so local dependency/code changes are included.

## When Would A Custom Build Be Needed?
- Only if you need custom OS/PHP extensions or image-level changes not present in `fireflyiii/core`.
