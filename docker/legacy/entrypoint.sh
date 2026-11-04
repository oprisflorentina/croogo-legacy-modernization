#!/bin/sh
# Does what the last screen of Croogo's web installer does, so the app
# starts in the same state on every machine without clicking through it.
set -e

cd /var/www/html/app/config
[ -f settings.yml ] || cp settings.yml.install settings.yml

exec "$@"
