# Use a base image that includes PHP and Node.js
FROM php:8.2-fpm

# Install system dependencies (non-PHP specific)
RUN apt-get update && apt-get install -y --no-install-recommends git zip unzip && rm -rf /var/lib/apt/lists/*

# Install Node.js and npm
RUN apt-get update && apt-get install -y --no-install-recommends nodejs npm && rm -rf /var/lib/apt/lists/*

# Install PHP extension development libraries
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev && rm -rf /var/lib/apt/lists/*

# Install database client libs
RUN apt-get update && apt-get install -y --no-install-recommends default-libmysqlclient-dev && rm -rf /var/lib/apt/lists/*

# Install XML and other libs
RUN apt-get update && apt-get install -y --no-install-recommends libxml2-dev libzip-dev libicu-dev libonig-dev && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install gd
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install xml


RUN docker-php-ext-install session
RUN docker-php-ext-install dom
RUN docker-php-ext-install ctype
RUN docker-php-ext-install fileinfo
RUN docker-php-ext-install intl
RUN docker-php-ext-install zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files for caching
COPY composer.json composer.lock ./

# Run composer install
RUN composer install --no-dev --optimize-autoloader

# Copy package.json for caching
COPY package.json package-lock.json ./

# Run npm install
RUN npm install

# Copy remaining application files
COPY . /var/www/html
RUN npm install
RUN npm run build

RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache
RUN php artisan migrate --force

# Expose port for PHP-FPM
EXPOSE 8000

# Start Laravel development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]