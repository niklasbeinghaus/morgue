#!/bin/sh
set -e;
$(which php-fpm) -R --nodaemonize &
/bin/wait-for-it.sh -t 120 127.0.0.1:9000 &&
/usr/sbin/nginx
#/usr/local/bin/php -d 'include_path=/usr/src/app:/usr/src/app/features' \
#              -d 'date.timezone="Europe/Berlin"' \
#              -t /usr/src/app/htdocs \
#              -S 127.0.0.1:9000