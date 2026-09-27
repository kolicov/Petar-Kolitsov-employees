# syntax=docker/dockerfile:1

# Stage 1: build the Vite/Tailwind assets
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json ./
RUN npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# Stage 2: PHP runtime
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Install dependencies first so this layer is cached between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN cp .env.example .env \
    && composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8000

# Generate an app key on first start (it is never baked into the image).
CMD ["sh", "-c", "grep -q '^APP_KEY=base64' .env || php artisan key:generate --force; exec php artisan serve --host=0.0.0.0 --port=8000"]
