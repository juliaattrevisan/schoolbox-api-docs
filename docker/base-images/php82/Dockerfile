# Base PHP Schoolbox image: will be used as a base for these images:
# * PHP-FPM
# * Daemons
#
# Will be uploaded to Schoolbox's container repository, so that this need not be
# rebuilt every time Docker Compose is run.

# Build args:
# ENV:  if prod or production, build with prod dependencies only;
#       otherwise, build with dev dependencies.
# ARCH: the processor architecture to build for (amd64, arm)
ARG ENV=dev
ARG ARCH

FROM php:8.2-alpine

# Install dependencies for building PECL extensions
RUN apk --no-cache add ${PHPIZE_DEPS} && \
    apk add --update linux-headers && \
    apk add libzip-dev zip unzip libxml2-dev oniguruma-dev icu-dev curl-dev openldap-dev \
            imagemagick-dev imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
            imap-dev \
            unixodbc-dev gnupg \
            bind-tools

# Install PECL extensions
RUN pecl install redis imagick pdo_sqlsrv excimer
RUN if [[ ! " prod production " =~ " $ENV " ]] ; then pecl install xdebug ; fi

# Install non-PECL extensions
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
    docker-php-ext-install imap

# Install ODBC driver
COPY ./bin/install-odbc-driver.sh /opt/bin/install-odbc-driver.sh
RUN /opt/bin/install-odbc-driver.sh

# Copy configuration files
COPY ./config/* /usr/local/etc/php/conf.d/
