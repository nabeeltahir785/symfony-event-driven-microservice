FROM php:8.3-fpm AS builder

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
        pcntl \
    && pecl install amqp rdkafka \
    && docker-php-ext-enable amqp rdkafka

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist 2>/dev/null || true

COPY app/ .
RUN composer dump-autoload --optimize 2>/dev/null || true

FROM php:8.3-fpm AS runtime

RUN set -eux; \
    apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        librabbitmq-dev \
        librdkafka-dev \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    docker-php-ext-install \
        pdo_pgsql \
        opcache \
        pcntl \
    && pecl install amqp rdkafka \
    && docker-php-ext-enable amqp rdkafka

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

EXPOSE 9000

CMD ["php-fpm"]
