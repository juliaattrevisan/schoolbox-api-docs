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

# Fix this so that xdebug is actually installed
RUN if [[ ! " prod production " =~ " $ENV " ]] ; then install-php-extensions xdebug ; fi

# Install ODBC driver
COPY ./bin/install-odbc-driver.sh /opt/bin/install-odbc-driver.sh
RUN /opt/bin/install-odbc-driver.sh

# Copy configuration files
COPY ./config/* /usr/local/etc/php/conf.d/
