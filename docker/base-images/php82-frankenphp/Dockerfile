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

FROM --platform=linux/${ARCH} dunglas/frankenphp:1.5-php8.2-bookworm

# Ensure ENV is accessible by this build step:
# otherwise, xDebug will be installed below.
ARG ENV

# Install additional extensions
# (not in https://github.com/shinsenter/php?tab=readme-ov-file#pre-installed-php-extensions)
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
    libxml

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

# Copy configuration files
COPY ./config/* /usr/local/etc/php/conf.d/
