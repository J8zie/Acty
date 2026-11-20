# Laravel API for Render (Docker). Build:  docker build -t actify-api .
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql pgsql opcache \
    && a2enmod rewrite headers

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-actify.ini
COPY docker/apache.conf /etc/apache2/conf-available/actify.conf
RUN a2enconf actify \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

ENV PORT=10000
EXPOSE 10000
CMD ["docker/start.sh"]
