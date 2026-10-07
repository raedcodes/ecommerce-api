#!/usr/bin/env bash

# Creates "<DB_DATABASE>_testing" so phpunit.xml uses the same database name under Sail and locally.

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_testing\`;
EOSQL

if [ -n "$MYSQL_USER" ]; then
mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_testing\`.* TO '$MYSQL_USER'@'%';
EOSQL
fi
