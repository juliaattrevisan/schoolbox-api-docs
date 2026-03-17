# FrankenPHP Schoolbox image

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG PHP_MAJOR_VERSION=8.4

FROM dunglas/frankenphp:1.12.1-php${PHP_MAJOR_VERSION}-trixie

ARG PHP_MAJOR_VERSION

# Ensure ENV is accessible by this build step:
# otherwise, xDebug will be installed below.
ARG ENV

# Install pdo_sqlsrv requirements
# (workaround from https://github.com/mlocati/docker-php-extension-installer/pull/1149#discussion_r2307219876)
RUN apt-get update && \
    apt-get install -qy --no-install-recommends gnupg jq && \
    curl -sSLf https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor --yes --output /usr/share/keyrings/microsoft-prod.gpg && \
    curl -sSLf https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list && \
    apt-get update && \
    ACCEPT_EULA=Y apt-get install -qy --no-install-recommends msodbcsql18

# Install additional extensions, + pdo_sqlsrv requirements
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
        gd \
        opcache && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install XDebug on non-production, also copy base config
RUN <<EOF
case "$ENV" in
  prod)
    ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
    ;;
  dev)
    install-php-extensions xdebug
    ln -s /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
    ;;
  *)
    echo "Invalid value of ENV build arg: if provided, must be one of (dev, prod)"
    exit 1
    ;;
esac
EOF

COPY ./usr/bin/* /usr/bin/
COPY ./usr/local/bin/* /usr/local/bin/

# Copy configuration files
COPY ./config/php/*.ini /usr/local/etc/php/conf.d/
COPY ./config/php/${ENV}/*.ini /usr/local/etc/php/conf.d/
COPY ./config/frankenphp/* /etc/frankenphp/

RUN \
    useradd www-data; \
    mkdir -p /var/lib/schoolbox; \
    chown -R www-data:www-data /var/lib/schoolbox /config/caddy /data/caddy;

EXPOSE 80 443

WORKDIR /usr/share/schoolbox
