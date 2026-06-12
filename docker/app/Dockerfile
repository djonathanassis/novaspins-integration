FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        libzip-dev \
        oniguruma-dev \
        autoconf \
        g++ \
        make \
    && docker-php-ext-install pdo pdo_mysql bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

# Tune PHP serialization precision to match the NovaSpins reconciliation
# tool's float-rounding semantics (4 significant digits) so we don't ship
# inconsistent JSON to their callback verifier.
RUN { \
        echo 'precision = 4'; \
        echo 'serialize_precision = 4'; \
    } > /usr/local/etc/php/conf.d/zz-novaspins-precision.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/bootstrap/cache \
             /var/www/html/storage/app/public \
             /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/testing \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage \
    && chmod -R ug+rwx /var/www/html/bootstrap/cache /var/www/html/storage

EXPOSE 9000

CMD ["sh", "-c", "chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage 2>/dev/null; chmod -R ug+rwx /var/www/html/bootstrap/cache /var/www/html/storage 2>/dev/null; exec php-fpm"]
