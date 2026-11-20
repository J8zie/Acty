#!/bin/sh
# Runs every time the container starts on Render.
set -e
cd /var/www/html

# Render gives us the port to listen on in $PORT.
sed -ri "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi
php artisan migrate --force              # creates / updates the tables
php artisan db:seed --class=AdminSeeder --force   # first admin from ADMIN_* (does nothing when they are not set)

php artisan config:cache
php artisan route:cache

exec apache2-foreground
