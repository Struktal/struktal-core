#!/bin/sh

env > /etc/environment

su -s /bin/sh nginx -c "php85 vendor/bin/evolve --config-loader /app/struktal/start.php --evolutions-directory /app/src/schema"

php-fpm85
nginx
crond

tail -f /var/log/nginx/access.log &
tail -f /var/log/nginx/error.log &
wait
