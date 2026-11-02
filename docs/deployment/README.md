# Production deployment and recovery

This directory packages the existing modular monolith; it does not split business modules into services. One release contains the Laravel API, employee SPA, customer PWA and Next public website. PostgreSQL 17 and Redis 7 are private Compose services. Only nginx is bound to host loopback. A host reverse proxy terminates HTTPS for three hostnames and forwards them, with the original `Host`, to `127.0.0.1:8080`.

The Windows development environment has no Docker CLI. The `deployment smoke` GitHub Actions workflow on `dev` builds the actual images and checks startup/routing, Horizon/scheduler, restricted database access, private files and an isolated backup/restore with FIFO and cash fixtures. It uses disposable runner volumes and generated credentials, not a production deployment. Check its recorded result before treating a container release as verified. The application builds, lint and behavior tests can also be run independently using the root README.

The [first Linux smoke run](https://github.com/sobirjon-swe/ocula/actions/runs/41385239088) passed on 2026-11-02: FIFO quantity 1/cost 300.00 and cash 1500.00 plus both attachment files matched after restore. Restore over the populated source database was refused. This verifies the recipes against disposable data; repeat the recovery drill with the actual business deployment before cutover.

## Environment separation

Run all commands below from `docs/deployment` on the deployment host with Docker Engine and Compose v2. Use a dedicated host account and a maintained Linux host. Do not build from a working directory containing unreviewed source.

```sh
cp .env.example .env
cp app.env.example .env.app
chmod 600 .env .env.app
```

Edit `.env` for Compose interpolation: separate PostgreSQL administrator, application database and Redis passwords; database name; three public DNS names; public canonical `SITE_URL`; loopback binding. The PostgreSQL application role is `optika`, a database owner without superuser privileges. The init script creates required extensions. It runs only when initializing a new data volume. Changing passwords in `.env` does not rotate credentials already stored in a live database; perform rotation using PostgreSQL administration first.

Edit `.env.app` for backend runtime only. Set `APP_KEY` to a new key generated once with the release's PHP runtime:

```sh
docker compose build app
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
```

Copy the displayed key into `.env.app` in a private terminal; preserve it in your secret manager for recovery. Do not run this command during routine upgrades or rotate the key without a data migration plan. Update `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` to the actual employee hostname. Cookies use HTTPS, HttpOnly, encryption and SameSite=Lax. Customer authentication uses a separate bearer token guard. Set Telegram bot token, username and webhook secret from your authorized bot configuration. Configure a production mail/SMS provider only when its account is available; blank external credentials do not simulate successful delivery.

Branch pages generate QR images locally. Set optional public `TELEGRAM_MINI_APP_URL` in `.env` to the bot's Mini App link (`https://t.me/your_bot/app`) for Telegram scan-to-check-in. Otherwise Compose supplies the customer hostname as the check-in target; an installed PWA needs a valid customer credential. In other hosting setups set server-side `CUSTOMER_APP_URL` (or `NEXT_PUBLIC_CUSTOMER_URL`). QR codes contain only branch routing information. Immediate check-in joins today's doctor queue; the public booking form requests a callback for a future appointment. Both paths remain separate.

No runtime secret enters a Docker build argument. Optional `VITE_YANDEX_MAPS_KEY` is a public browser key, passed only to the employee build; restrict it to the employee hostname at Yandex and rebuild `web` after changing it. Dockerfiles copy named source directories, not local `.env`, vendor, test fixtures or storage. SPA builds use same-origin `/api/v1`; the Next server calls private `web:8081`. PostgreSQL, Redis and PHP-FPM have no published ports. nginx substitutes only the three hostname variables. The web server serves only `storage/app/public`; private uploads remain accessible through authorized API flows.

The backend runtime template includes `TELEGRAM_DIRECTOR_CHAT_ID` for daily director summaries and the optional SMS fallback settings. Enable SMS only after setting the authorized HTTPS endpoint, username, password and originator in `.env.app`; rebuild is unnecessary for these runtime values, but recreate app/worker/scheduler containers so they load the new environment. Verify delivery using staging recipients first.

## First start

Create DNS and HTTPS certificates for `PUBLIC_HOST`, `ADMIN_HOST` and `CUSTOMER_HOST`. Configure your host TLS reverse proxy to preserve `Host` and forward to loopback port 8080; restrict direct access to the backend port. The supplied nginx configuration assumes every browser-facing request reached it through this HTTPS edge. Keep `.env.app` secure-cookie settings enabled. All three apps must be reachable over HTTPS before testing Telegram or service workers.

```sh
docker compose build
docker compose up -d postgres redis
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force
docker compose run --rm app php artisan optika:create-director
docker compose up -d
docker compose ps
docker compose exec app php artisan schedule:list
docker compose exec app php artisan horizon:status
```

The director command prompts for name, international phone, active branch, hidden password (minimum 12 characters), and matching confirmation. It refuses existing phones, including deleted accounts, so it cannot silently promote an employee. There are no default production credentials. Production seeding creates permissions/reference data, not demo employees. Replace seeded branch names, contact details, locations, services and prices with verified business values before directing customers to the website.

Check login/logout and branch isolation; open all four public locale routes; submit a staging appointment; authenticate a staging Telegram customer; confirm order/debt/prescription access; test a queued notification through the configured bot. Verify private files cannot be retrieved from `/storage`. Test installation/offline shell on the actual supported phones; API data must never appear from a service-worker cache. Review logs with `docker compose logs --tail=100 app worker scheduler web landing`; do not publish logs containing customer information or credentials.

## Upgrades and rollback

Take and verify a backup first. Prepare one reviewed release and run its CI checks. Build images before the maintenance window. Stop workers/scheduler and prevent new writes at the HTTPS edge, then run migrations once. Recreate app, worker, scheduler, landing and web together so static assets/API contracts stay aligned:

```sh
docker compose build
docker compose stop worker scheduler
docker compose run --rm app php artisan migrate --force
docker compose up -d --force-recreate app worker scheduler landing web
```

Reopen traffic only after health and business smoke checks. Every process is replaced, so workers load the new code. Do not cache configuration into an image using deployment secrets. Runtime `config:cache` is optional and must be regenerated within each container on every runtime environment change. Do not expose the Horizon dashboard publicly; its authorization must remain employee restricted.

Rollback the application to the previous reviewed images only when its schema remains compatible. Do not run destructive migration rollback automatically. For incompatible schema/data changes, restore into a new isolated deployment and switch traffic after verifying it. Never delete production named volumes to fix a startup issue.

## Backup

```sh
sh backup.sh
```

This creates a PostgreSQL custom-format dump and the application's attachment archive under ignored `backups/` with restrictive permissions. The database dump is transaction-consistent. To align files and database references exactly, pause writes and workers while taking the pair; otherwise a newly uploaded attachment can fall between snapshots. Back up `.env.app`/`APP_KEY` and required infrastructure secrets separately in a secret manager. Encrypt backups and copy them off-host with a retention policy. Include a daily backup schedule in infrastructure operations, monitor exit status, and rehearse recovery regularly. A backup is not proven recoverable until restored successfully.

## Restore drill or incident recovery

Use a separate hostname/network and new Compose project/data volumes. Keep traffic blocked and workers stopped; do not migrate or seed the empty target before loading its dump. Copy the original `APP_KEY` and production secrets from your secret store. Start only PostgreSQL and Redis, then:

```sh
docker compose up -d postgres redis
CONFIRM_RESTORE=RESTORE_INTO_EMPTY_DATABASE sh restore.sh backups/TIMESTAMP.dump backups/TIMESTAMP.storage.tar.gz
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

The script refuses a database containing public application tables. It does not drop or overwrite an existing database. Use only a trusted archive from your backup system. Validate all five branch balances, FIFO stock totals, open order/debt counts, latest payments, file access, and notification queues before switching traffic. Keep the original incident database for investigation. Redis queues are operational state; decide which pending jobs may safely be replayed after restoring the database, using the application's idempotency protections. Restore of real production data must remain isolated from external notification delivery during the drill.

## Day-to-day monitoring

Monitor disk space, database connections, failed jobs, Horizon worker status and scheduler execution. Run `php artisan queue:failed` inside `app` to inspect failures; investigate before retrying. Watch for accumulating unreceived transfers, overdue orders/debts and driver balances. Keep host, base images and package locks reviewed and updated together. PostgreSQL/Redis volumes persist independently from application containers; never use `docker compose down --volumes` on production.
