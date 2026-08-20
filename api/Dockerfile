FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip intl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan filament:assets --no-interaction

RUN chmod +x docker/start.sh \
    && chown -R www-data:www-data storage bootstrap/cache public

ENV PORT=8080
EXPOSE 8080

CMD ["docker/start.sh"]
