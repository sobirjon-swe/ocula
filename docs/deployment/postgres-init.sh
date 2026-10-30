#!/bin/sh
set -eu
# Separate database owner from the PostgreSQL superuser. Runs only for a new volume.
psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  --set=ON_ERROR_STOP=1 --set=app_password="$APP_DB_PASSWORD" --set=db_name="$POSTGRES_DB" <<'SQL'
CREATE ROLE optika LOGIN PASSWORD :'app_password';
ALTER DATABASE :"db_name" OWNER TO optika;
GRANT USAGE, CREATE ON SCHEMA public TO optika;
SET ROLE optika;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
SQL
