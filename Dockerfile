# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY resources ./resources
COPY vite.config.* ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

FROM php:8.3-cli-alpine
RUN apk add --no-cache \
        libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev oniguruma-dev icu-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_mysql zip gd bcmath intl exif

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN php artisan config:clear

EXPOSE 10000
CMD sh -c "php artisan migrate --force && php artisan storage:link || true && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"
