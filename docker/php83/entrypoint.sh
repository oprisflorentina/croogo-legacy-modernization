#!/bin/sh
# Same start-up as the legacy container. app/tmp is a separate volume here,
# so cache files written by PHP 5.6 and PHP 8.3 never mix.
set -e

cd /var/www/html/app
for dir in cache/models cache/persistent cache/queries cache/views logs sessions tests; do
    mkdir -p "tmp/$dir"
done
chown -R www-data:www-data tmp

[ -f config/settings.yml ] || cp config/settings.yml.install config/settings.yml

exec "$@"
