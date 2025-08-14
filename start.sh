#!/bin/bash

# Clear config cache
php artisan config:clear

# Discover packages
php artisan package:discover --ansi

# Run migrations (if needed)
php artisan migrate --force

# Start Nginx and PHP-FPM
nginx -g 'daemon off;' & php-fpm

# Keep the container running
wait