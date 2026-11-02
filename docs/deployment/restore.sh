#!/bin/sh
set -eu
cd "$(dirname "$0")"
if [ "$#" -ne 2 ] || [ "${CONFIRM_RESTORE:-}" != 'RESTORE_INTO_EMPTY_DATABASE' ]; then
  echo 'Usage: CONFIRM_RESTORE=RESTORE_INTO_EMPTY_DATABASE sh restore.sh DB.dump STORAGE.tar.gz' >&2
  echo 'Restore only to a new empty deployment/volume; never overwrite a running database.' >&2
  exit 1
fi
test -f "$1"
test -f "$2"
# Fail closed if there are any existing application tables.
tables=$(docker compose exec -T postgres sh -c 'PGPASSWORD="$APP_DB_PASSWORD" psql -h 127.0.0.1 -U optika -d "$POSTGRES_DB" -Atc "SELECT count(*) FROM pg_tables WHERE schemaname = '\''public'\''"')
if [ "$tables" != 0 ]; then echo 'Database is not empty; restore refused.' >&2; exit 1; fi
docker compose stop worker scheduler
docker compose exec -T postgres sh -c 'PGPASSWORD="$APP_DB_PASSWORD" pg_restore -h 127.0.0.1 -U optika -d "$POSTGRES_DB" --no-owner --no-acl --exit-on-error' < "$1"
docker compose run --rm -T --no-deps app tar -xzf - -C storage < "$2"
echo 'Restored. Verify APP_KEY, run migrations, validate balances/files, then start workers and traffic.'
