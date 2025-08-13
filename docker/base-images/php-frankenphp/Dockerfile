# FrankenPHP Schoolbox image

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG PHP_VERSION

# TODO should this support ARCH as well?
FROM dunglas/frankenphp:1.8-php${PHP_VERSION}-bookworm

# Ensure ENV is accessible by this build step:
# otherwise, xDebug will be installed below.
ARG ENV

# Install additional extensions
# (not in https://github.com/shinsenter/php?tab=readme-ov-file#pre-installed-php-extensions)
RUN apt-get update && apt-get upgrade -y && \
    install-php-extensions \
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
    opcache && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install XDebug on non-production, also copy base config
RUN <<EOF
case "$ENV" in
  prod | production)
    ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
    ;;
  *)
    install-php-extensions xdebug
    ln -s /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
    ;;
esac
EOF

COPY ./usr/bin/* /usr/bin/
COPY ./usr/local/bin/* /usr/local/bin/

# Cheat by linking /usr/local/bin/php to /usr/bin/php:
# all of our scripts have shebang lines referencing that.
RUN ln -s /usr/local/bin/php /usr/bin/php
# Copy configuration files
COPY ./config/php/* /usr/local/etc/php/conf.d/
COPY ./config/php${PHP_VERSION}/* /usr/local/etc/php/conf.d/
COPY ./config/imagemagick/* /etc/ImageMagick-6/
COPY ./config/frankenphp/* /etc/frankenphp/


EXPOSE 80 443

WORKDIR /usr/share/schoolbox
