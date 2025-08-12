# Base PHP Schoolbox image: will be used as a base for these images:
# * PHP-FPM
# * Daemons
#
# Will be uploaded to Schoolbox's container repository, so that this need not be
# rebuilt every time Docker Compose is run.

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG ARCH=amd64
ARG PHP_VERSION

FROM --platform=linux/${ARCH} php:${PHP_VERSION}-cli-bookworm

# Use install-php-extensions script, and install extensions with that
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Copy configuration files
RUN <<EOF
case "$ENV" in
  prod | production)
    ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
    ;;
  *)
    ln -s /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
    ;;
esac
EOF
COPY ./config/php/* /usr/local/etc/php/conf.d/

# Copy scripts from puppet-alaress as-is for now
COPY ./usr/bin/* /usr/bin/
COPY ./usr/local/bin/* /usr/local/bin/

# Cheat by linking /usr/local/bin/php to /usr/bin/php:
# all of our scripts have shebang lines referencing that.
RUN ln -s /usr/local/bin/php /usr/bin/php

# Install PHP dependencies, as well as likely requirements, as per
# https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2584313884/Services+within+Schoolbox#Execution-of-shell-commands
# Command-line tools will only be installed in the CLI box, though.
RUN install-php-extensions \
    imagick \
    pdo_mysql \
    pdo_sqlsrv \
    excimer \
    curl \
    ldap \
    dom \
    sockets \
    imap \
    redis \
    readline \
    zip \
    pcntl \
    posix \
    openssl \
    json \
    simplexml \
    mbstring \
    intl \
    xmlreader \
    dom \
    exif \
    zlib \
    fileinfo \
    libxml \
    opcache

# Copy non-broken Imagemagick config, only after installing Imagemagick
COPY ./config/imagemagick/* /etc/ImageMagick-6/

# Install XDebug on non-production images
RUN <<EOF
case "$ENV" in
  prod | production)
    ;;
  *)
    install-php-extensions xdebug
    ;;
esac
EOF
