FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader || true

COPY api/ .

RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/api/storage /var/www/api/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
