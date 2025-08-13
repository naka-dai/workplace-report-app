# Use a base image that includes PHP and Node.js
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nodejs \
    npm \
    git \
    zip \
    unzip \
    # Add common PHP extensions for Laravel
    php82-pdo_mysql \
    php82-gd \
    php82-mbstring \
    php82-xml \
    php82-json \
    php82-tokenizer \
    php82-session \
    php82-dom \
    php82-ctype \
    php82-fileinfo

# Install Composer
COPY --from=composer/composer:latest-bin /usr/bin/composer /usr/local/bin/composer

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
