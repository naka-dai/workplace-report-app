# Use a base image that includes PHP and Node.js
FROM php:8.2-fpm

# Install all system dependencies in one go
RUN apt-get update && apt-get install -y --no-install-recommends git zip unzip nodejs npm libpng-dev libjpeg-dev libfreetype6-dev default-libmysqlclient-dev libxml2-dev libzip-dev libicu-dev libonig-dev && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql gd mbstring xml session dom ctype fileinfo intl zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files for caching
COPY composer.json composer.lock ./

# Run composer install (without scripts)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy package.json for caching
COPY package.json package-lock.json ./

# Run npm install
RUN npm install

# Copy remaining application files
COPY . /var/www/html

# Run build commands
RUN npm run build \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force

# Expose port for Laravel (default 8000, Render maps $PORT to this)
EXPOSE 8000

# Start Laravel development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]