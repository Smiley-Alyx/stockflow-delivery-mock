FROM php:8.3-cli-alpine AS base

RUN apk add --no-cache \
    sqlite-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_sqlite pcntl sockets \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction

FROM base AS runtime

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize \
    && chmod +x docker/entrypoint.sh \
    && chmod +x bin/consume-requests.php

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
