FROM php:8.2-fpm-alpine

RUN apk --no-cache add ${PHPIZE_DEPS} && \
    apk add --update linux-headers && \
    pecl install redis xdebug

RUN apk add libzip-dev zip unzip libxml2-dev oniguruma-dev icu-dev curl-dev openldap-dev

# https://hub.docker.com/_/php for directions on installing extensions
RUN docker-php-ext-install zip && \
    docker-php-ext-install pcntl && \
    docker-php-ext-install pdo && \
#    docker-php-ext-install openssl && \
#    docker-php-ext-install imagick && \
    docker-php-ext-install mbstring && \
    docker-php-ext-install intl && \
    docker-php-ext-install curl && \
    docker-php-ext-install ldap && \
    docker-php-ext-install dom && \
    docker-php-ext-enable redis && \
    docker-php-ext-enable xdebug
