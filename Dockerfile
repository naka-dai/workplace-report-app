# Use a base image that includes PHP and Node.js
FROM php:8.2-fpm

# Install all system dependencies in one go
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    zip \
    unzip \
    nodejs \
    npm \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-libmysqlclient-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql \
    && docker-php-ext-install gd \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install xml \
    && docker-php-ext-install session \
    && docker-php-ext-install dom \
    && docker-php-ext-install ctype \
    && docker-php-ext-install fileinfo \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip

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

# Set permissions for storage and bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache     && chmod -R 777 storage bootstrap/cache

# Configure Nginx
COPY nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -rf /etc/nginx/sites-enabled/default.bak # Clean up default symlink if it exists

# Run build commands
RUN npm run build \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force

# Expose port for Nginx (default HTTP port)
EXPOSE 80

# Start Nginx and PHP-FPM
CMD ["/bin/bash", "-c", "nginx -g 'daemon off;' & php-fpm"]