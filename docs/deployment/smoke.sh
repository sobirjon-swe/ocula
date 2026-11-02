#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

# This harness creates/removes volumes. Refuse arbitrary hosts/project names.
[[ "${CI:-}" == true && "${GITHUB_ACTIONS:-}" == true ]] || { echo 'Run only on a disposable GitHub Actions runner.' >&2; exit 1; }
run_id="${GITHUB_RUN_ID:?}"; attempt="${GITHUB_RUN_ATTEMPT:?}"
[[ "$run_id" =~ ^[0-9]+$ && "$attempt" =~ ^[0-9]+$ ]] || exit 1
source_project="ocula-ci-${run_id}-${attempt}"
target_project="${source_project}-restore"
export COMPOSE_PROJECT_NAME="$source_project"
cleanup() {
  status=$?
  if [[ "$status" != 0 ]]; then docker compose logs --no-color --tail=150 || true; fi
  docker compose --project-name "$target_project" down --volumes --remove-orphans || true
  docker compose --project-name "$source_project" down --volumes --remove-orphans || true
  exit "$status"
}
trap cleanup EXIT
umask 077
[[ ! -e .env && ! -e .env.app ]] || { echo 'Refusing to replace existing deployment configuration.' >&2; exit 1; }
cp .env.example .env
cp app.env.example .env.app
sed -i "s/^POSTGRES_DB=.*/POSTGRES_DB=optika_ci/; s/^PG_ADMIN_PASSWORD=.*/PG_ADMIN_PASSWORD=$(openssl rand -hex 24)/; s/^DB_PASSWORD=.*/DB_PASSWORD=$(openssl rand -hex 24)/; s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=$(openssl rand -hex 24)/" .env
printf '\nAPP_KEY=base64:%s\n' "$(openssl rand -base64 32)" >> .env.app
docker compose config --quiet
docker compose build
docker compose up -d --wait postgres redis
docker compose run --rm -T app php artisan migrate --force
docker compose run --rm -T app php artisan db:seed --force
docker compose up -d --no-build --wait --wait-timeout 240
docker compose exec -T -e OPTIKA_SMOKE=1 app php /dev/stdin seed < smoke-fixture.php
docker compose exec -T app php artisan horizon:status
docker compose exec -T app php artisan schedule:list
superuser=$(docker compose exec -T postgres sh -c 'PGPASSWORD="$APP_DB_PASSWORD" psql -h 127.0.0.1 -U optika -d "$POSTGRES_DB" -Atc "SELECT rolsuper FROM pg_roles WHERE rolname = current_user"')
[[ "$superuser" == f ]]
curl --fail --silent --show-error -H 'Host: admin.optika.example.com' http://127.0.0.1:8080/api/v1/ping > /dev/null
curl --fail --silent --show-error -H 'Host: admin.optika.example.com' http://127.0.0.1:8080/login | grep -q '<div id="root"'
curl --fail --silent --show-error -H 'Host: customer.optika.example.com' http://127.0.0.1:8080/ | grep -q '<div id="root"'
for locale in uz-latn uz-cyrl ru en; do curl --fail --silent --show-error -H 'Host: optika.example.com' "http://127.0.0.1:8080/$locale" > /dev/null; done
[[ "$(curl --silent --show-error -H 'Host: admin.optika.example.com' http://127.0.0.1:8080/storage/ci-smoke-public.txt)" == 'public restore sentinel' ]]
[[ "$(curl --silent --show-error -o /dev/null -w '%{http_code}' -H 'Host: admin.optika.example.com' http://127.0.0.1:8080/storage/ci-smoke-private.txt)" == 404 ]]

# Pause writers for an attachment/database-consistent backup pair.
docker compose stop worker scheduler
sh backup.sh
dump=$(find backups -name '*.dump' -type f | sort | tail -n 1)
archive="${dump%.dump}.storage.tar.gz"
if CONFIRM_RESTORE=RESTORE_INTO_EMPTY_DATABASE sh restore.sh "$dump" "$archive" > refusal.log 2>&1; then
  echo 'Restore incorrectly accepted a nonempty database.' >&2; exit 1
fi
grep -q 'Database is not empty; restore refused.' refusal.log

# New project = distinct database/Redis/attachment volumes, same preserved APP_KEY.
docker compose --project-name "$target_project" build app
docker compose --project-name "$target_project" up -d --wait postgres redis
COMPOSE_PROJECT_NAME="$target_project" CONFIRM_RESTORE=RESTORE_INTO_EMPTY_DATABASE sh restore.sh "$dump" "$archive"
docker compose --project-name "$target_project" run --rm -T --no-deps app php artisan migrate --force
docker compose --project-name "$target_project" run --rm -T --no-deps -e OPTIKA_SMOKE=1 app php /dev/stdin verify < smoke-fixture.php
echo 'Container startup, routing, private storage, FIFO/cash state and isolated backup/restore passed.'
