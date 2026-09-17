FROM dunglas/frankenphp:1-php8.4

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && setcap -r /usr/local/bin/frankenphp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

ENV APP_ENV=prod
ENV SERVER_NAME=:8080
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

RUN php bin/console importmap:install
RUN php bin/console asset-map:compile
RUN php bin/console cache:warmup --env=prod

EXPOSE 8080

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]