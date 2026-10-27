# COMPOSER DEPENDENCIES STAGE
FROM composer:latest AS composer-builder
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# FRONTEND BUILD STAGE
FROM node:24-alpine AS node-builder
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@latest --activate
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml* ./
RUN pnpm install --frozen-lockfile

COPY . .
COPY --from=composer-builder /var/www/html/vendor ./vendor
RUN pnpm build

# RUNTIME STAGE
FROM php:8.4-fpm-alpine
WORKDIR /var/www/html

# Install dependencies and precompiled PHP extensions via official installer (fast & lightweight)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN apk add --no-cache curl git \
    && install-php-extensions intl bcmath pdo_mysql zip opcache pcntl

# Composer binary
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
COPY --from=composer-builder /var/www/html/vendor ./vendor

# Copy application code and set permissions
COPY --chown=www-data:www-data . .
COPY --from=node-builder --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
