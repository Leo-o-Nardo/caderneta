FROM php:8.4-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql redis intl zip bcmath pcntl

RUN useradd -m -u 1000 dev \
    && mkdir -p /var/www/html/vendor \
    && chown -R dev:dev /var/www/html

WORKDIR /var/www/html
USER dev