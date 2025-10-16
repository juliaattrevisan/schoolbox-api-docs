FROM php:8.4-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get upgrade -y && \
    install-php-extensions \
        intl \
        gd \
        opcache \
        zip \
        calendar \
        sockets \
        igbinary \
        redis

RUN rm -rf /var/www/html \
    && chmod 0777 /tmp/

# Install Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copy configs
COPY ./config/*.ini /usr/local/etc/php/conf.d/
