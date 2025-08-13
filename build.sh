#!/usr/bin/env bash

# Enable strict mode: exit on error, treat unset variables as error,
# print commands and their arguments as they are executed,
# and ensure pipeline failures are propagated.
set -euxo pipefail

echo "Downloading composer.phar..."
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"


# Run your Laravel build commands
php composer.phar install --no-dev
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# If you have frontend assets (Vite) and public/build is NOT committed:      
npm install
npm run build

# Run database migrations
php artisan migrate --force