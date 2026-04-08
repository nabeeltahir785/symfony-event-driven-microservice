FROM php:8.3-fpm

RUN set -eux; \
    apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        libpq-dev \
        librabbitmq-dev \
        librdkafka-dev \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    docker-php-ext-install \
        pdo_pgsql \
        opcache \
    && pecl install amqp rdkafka \
    && docker-php-ext-enable amqp rdkafka

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

EXPOSE 9000

CMD ["php-fpm"]
