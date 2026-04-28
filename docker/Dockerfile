FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        opcache \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/aichat.ini

RUN mkdir -p /var/www/html/uploads /var/www/html/storage/rate_limits \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/storage /var/www/html/config \
    && chmod -R 755 /var/www/html/uploads /var/www/html/storage /var/www/html/config
