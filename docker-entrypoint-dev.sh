#!/bin/sh
# Start playgroundu: config/local.neon z proměnných, schéma DB ze StORM entit, ukázková data.
set -eu

cd /var/www/backoffice/playground

cat > config/local.neon <<NEON
parameters:
	db:
		host: '${DB_HOST:-backoffice-db}'
		dbname: '${DB_NAME:-backoffice}'
		user: '${DB_USER:-backoffice}'
		password: '${DB_PASS:-backoffice}'
	playgroundUser: '${PLAYGROUND_USER:-admin}'
	playgroundPassword: '${PLAYGROUND_PASSWORD:-backoffice}'

http:
	proxy: [10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16]
NEON

# Cache DI kontejneru je ze starého image / jiné konfigurace — při startu vždy čistá.
rm -rf temp/cache
mkdir -p temp/sessions log
chown -R www-data:www-data temp log

# Pod www-data, ať po sobě nenechá soubory, na které web nesáhne.
su -s /bin/sh www-data -c 'COMPOSER_HOME=/tmp/composer composer sync-database --no-interaction' || echo "sync-database selhal, web startuje dál"
su -s /bin/sh www-data -c 'php bin/seed.php' || echo "seed selhal, web startuje dál"

exec "$@"
