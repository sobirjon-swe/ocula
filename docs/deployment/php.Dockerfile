FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev libxml2-dev libzip-dev unzip git \
    && docker-php-ext-install -j$(nproc) bcmath pdo_pgsql mbstring dom xml xmlreader zip pcntl opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/backend
# Explicit copies keep local .env, storage, tests and vendor outside the image.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY backend/app ./app
COPY backend/bootstrap/app.php backend/bootstrap/providers.php ./bootstrap/
COPY backend/config ./config
COPY backend/database ./database
COPY backend/public ./public
COPY backend/routes ./routes
COPY backend/lang ./lang
COPY backend/artisan ./artisan
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && mkdir -p bootstrap/cache storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docs/deployment/php-entrypoint.sh /usr/local/bin/optika-entrypoint
RUN chmod +x /usr/local/bin/optika-entrypoint
USER www-data
ENTRYPOINT ["optika-entrypoint"]
CMD ["php-fpm"]
