# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

FROM php:8.2-cli AS base
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libzip-dev unzip \
    && docker-php-ext-install pdo pdo_sqlite zip sockets \
    && rm -rf /var/lib/apt/lists/*
COPY --from=vendor /app/vendor /var/www/html/vendor
WORKDIR /var/www/html

FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
COPY . .
RUN composer install --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080

FROM base AS production
ENV APP_ENV=production
COPY composer.json composer.lock ./
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN php -r "file_exists('.env') || copy('.env.example', '.env');" || true \
    && php artisan config:clear || true
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
