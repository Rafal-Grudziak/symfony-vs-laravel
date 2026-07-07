FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    libzip-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/memory.ini

WORKDIR /var/www/html
