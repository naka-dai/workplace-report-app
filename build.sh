#!/usr/bin/env bash

# Install Composer (if not already available)
if ! command -v composer &> /dev/null
then
    echo "Composer not found, installing..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer  
    php -r "unlink('composer-setup.php');"
fi

# Run your Laravel build commands
composer install --no-dev
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# If you have frontend assets (Vite) and public/build is NOT committed:      
# npm install
# npm run build

# Run database migrations
php artisan migrate --force