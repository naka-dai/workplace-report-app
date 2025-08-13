# Use a base image that includes PHP and Node.js
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends 
    nodejs 
    npm 
    git 
    zip 
    unzip 
    # Add common PHP extensions for Laravel
    php-pdo-mysql 
    php-gd 
    php-mbstring 
    php-xml 
    php-json 
    php-tokenizer 
    php-session 
    php-dom 
    php-ctype 
    php-fileinfo 
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Run build commands
RUN composer install --no-dev --optimize-autoloader \
    && npm install \
    && npm run build \
    && php artisan optimize:clear \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force

# Expose port for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
