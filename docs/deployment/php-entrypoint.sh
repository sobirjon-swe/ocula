#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ]; then
  echo 'APP_KEY is required. Generate it once and preserve it with encrypted backups.' >&2
  exit 1
fi
php artisan package:discover --ansi
exec "$@"
