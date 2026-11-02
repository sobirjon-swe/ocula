#!/bin/sh
set -eu
cd "$(dirname "$0")"
umask 077
stamp=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p backups
temporary="backups/$stamp.dump.partial"
trap 'rm -f "$temporary"' EXIT HUP INT TERM
docker compose exec -T postgres sh -c 'PGPASSWORD="$APP_DB_PASSWORD" pg_dump -h 127.0.0.1 -U optika -d "$POSTGRES_DB" -Fc --no-owner --no-acl' > "$temporary"
test -s "$temporary"
mv "$temporary" "backups/$stamp.dump"
# Public and private attachments. The APP_KEY is backed up separately in your secret store.
docker compose exec -T app tar -czf - -C storage app > "backups/$stamp.storage.tar.gz"
printf 'Backup written to backups/%s.*; copy encrypted files off this host.\n' "$stamp"
