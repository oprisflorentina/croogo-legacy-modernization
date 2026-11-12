#!/bin/sh
# var/ (Symfony's cache and logs) is a volume, so it starts out owned by
# root. PHP-FPM's workers run as www-data and have to write there.
set -e

cd /var/www/symfony
mkdir -p var/cache var/log var/share
chown -R www-data:www-data var

exec "$@"
