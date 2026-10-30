# OPTIKA

Optical retail operations for five branches: Laravel API, employee SPA, customer PWA/Telegram Mini App, and SSR public website. [PROJECT.md](docs/PROJECT.md) defines the business rules and architecture.

## Development

Required: PHP 8.4+ with `bcmath`, `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip`; Composer 2; PostgreSQL 16+; Node.js 22+ and npm. Redis is required in production; local Windows development can use database cache/queues. The dependency lock targets PHP 8.4 so CI, containers and local installs use a consistent minimum.

Create separate databases named `optika` and `optika_test`. The database owner must be able to enable `pg_trgm` and `unaccent` (or have the DBA create these extensions first). Do not point tests at the development or production database.

```powershell
cd backend
composer install
Copy-Item .env.example .env
# Edit database credentials and APP_URL before continuing.
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Configure initial branch, employee and catalog information using the seeded permissions. Review `backend/database/seeders` before seeding a real environment. In another terminal run `php artisan queue:work` from `backend`, and `php artisan schedule:work` in another. A worker processes notifications; the scheduler triggers reminders and daily jobs. Do not run Horizon on Windows.

Local/testing seeding includes demo staff: director `+998901000001`, password `password`. The catalog stays empty by default. For disposable local demonstrations only, run `php artisan db:seed --class=DemoScenarioSeeder` to add sample products/stock/customer. Demo seeders refuse other environments; production uses the director creation command in the deployment guide.

From the repository root:

```powershell
npm run install:frontends
npm run dev:admin
# Separate terminals:
npm run dev:customer
npm run dev:landing
```

Admin runs on port 5173, customer on 5174, landing on 3000. Vite forwards `/api` to `http://localhost:8000`; set `VITE_BACKEND_URL` in each SPA's local `.env` if needed. Use the same hostname consistently (`localhost` versus `127.0.0.1`) for Sanctum cookies. The landing uses server-only `BACKEND_API_URL=http://localhost:8000/api/v1` and `SITE_URL=http://localhost:3000` in `landing/.env.local`.

Customer sign-in uses Telegram signed `initData`; set the backend Telegram token and configure the bot's Mini App HTTPS URL. No public registration form is provided. After Telegram sign-in, the installed PWA can reuse its validated credential. Never paste real tokens into source control or build arguments.

The public website redirects `/` to `/uz-latn`; `/uz-latn`, `/uz-cyrl`, `/ru`, and `/en` each expose home, branches, prices and booking. Uzbek Cyrillic is derived from Latin source copy. Customer offline storage caches only static application assets; authenticated API responses and profile details are not persisted in that cache. Offline operations are not represented as successful server writes.

## Checks

```powershell
npm run check
cd backend
# Set DB_* for the separate optika_test database, or configure .env.testing.
composer ci:check
```

Frontend checks run lint, production builds, driver queue tests, customer cache/transliteration tests, and production SSR/booking smoke tests. The landing smoke test starts its own mock API and Next server on ports 18001 and 3101. It tests no live business data. Backend checks run Pint, PHPStan and PostgreSQL-backed tests.

Production packaging, environment separation, migrations, TLS, workers, backup and recovery: [deployment guide](docs/deployment/README.md). Container recipes are supplied for review; deployment requires real infrastructure configuration and is never automatic.
