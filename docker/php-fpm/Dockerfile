FROM php:8.2-fpm-alpine

RUN apk --no-cache add ${PHPIZE_DEPS} && \
    apk add --update linux-headers && \
    apk add libzip-dev zip unzip libxml2-dev oniguruma-dev icu-dev curl-dev openldap-dev \
            imagemagick-dev imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
            unixodbc-dev && \
    pecl install redis xdebug imagick pdo_sqlsrv \

# https://hub.docker.com/_/php for directions on installing extensions
RUN docker-php-ext-install zip && \
    docker-php-ext-install pcntl && \
    docker-php-ext-install pdo && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-install mbstring && \
    docker-php-ext-install intl && \
    docker-php-ext-install curl && \
    docker-php-ext-install ldap && \
    docker-php-ext-install dom && \
    docker-php-ext-install sockets && \
    docker-php-ext-install exif && \
    docker-php-ext-enable redis && \
    docker-php-ext-enable xdebug && \
    docker-php-ext-enable imagick && \
    docker-php-ext-enable pdo_sqlsrv

# Install ODBC driver
COPY bin/install-odbc-driver.sh /opt/bin/install-odbc-driver.sh
RUN /opt/bin/install-odbc-driver.sh
