FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libsqlite3-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_sqlite opcache sockets \
    && pecl install msgpack \
    && docker-php-ext-enable msgpack \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    || composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/cache var/runtime storage/framework/cache storage/logs logs public/storage \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh bin/durin bin/durins-forge \
    && if [ ! -f .env ]; then cp .env.example .env; fi \
    && vendor/bin/forge server:install || true \
    && php bin/durin optimize || true

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost \
    DB_CONNECTION=sqlite \
    DB_FILE=database.sqlite \
    CONTAINER_MODE=compiled \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["serve"]
