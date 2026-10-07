FROM php:8.4-fpm

RUN apt-get update && apt-get install -y curl git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql mysqli zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -m -u 1000 -s /bin/bash vscode