# FRONTED BUILD STAGE

FROM node:24-alpine AS node-builder
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@latest --activate
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm build

# Set the base image and working directory

FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Install dependencies and PHP extensions

RUN apk add --no-cache \
    icu-libs \
    libzip \
    curl \
    git \
    --virtual .build-deps \
    autoconf \
    gcc \
    g++ \
    make \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
    intl \
    bcmath \
    pdo_mysql \
    zip \
    opcache \
    pcntl \
    && apk del .build-deps

# Composer

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code and set permissions

COPY --chown=www-data:www-data . .
COPY --from=node-builder --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose port and set the default command

EXPOSE 9000
CMD ["php-fpm"]