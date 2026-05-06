# syntax=docker/dockerfile:1
FROM php:8.2-cli AS vendor
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libsqlite3-dev libzip-dev unzip git \
    && docker-php-ext-install pdo pdo_sqlite zip sockets \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

FROM php:8.2-cli AS base
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libsqlite3-dev libzip-dev unzip \
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
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
