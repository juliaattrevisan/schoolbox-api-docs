FROM php:8.3-fpm

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
        redis \
        xdebug

RUN rm -rf /var/www/html \
    && chmod 0777 /tmp/

# Install an older Composer version (2.2 = current LTS):
# devkit currently contains a script which is incompatible with 2.8
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Copy configs
COPY ./config/*.ini /usr/local/etc/php/conf.d/
