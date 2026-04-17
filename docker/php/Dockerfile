# PHP-FPM з необхідними розширеннями
FROM php:8.2-fpm

# Встановлення системних залежностей
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    intl \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Встановлення робочої директорії
WORKDIR /var/www/html

# Права доступу
RUN chown -R www-data:www-data /var/www/html
