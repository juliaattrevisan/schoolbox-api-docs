# FrankenPHP Schoolbox image

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG ARCH=amd64
ARG PHP_MAJOR_VERSION=8.4

# Currently alpine can drop-in replace bookworm:
# just replace "bookworm" with "alpine" in the `FROM` line below.
# Image size: alpine 355MB, bookworm 653MB.
# Attempting to `apt install` required packages and only copy compiled
# extensions actually bloats the bookworm image to 743MB.
FROM --platform=linux/${ARCH} dunglas/frankenphp:1.8-php${PHP_MAJOR_VERSION}-bookworm

# Ensure ENV is accessible by this build step:
# otherwise, xDebug will be installed below.
ARG ENV
ARG PHP_MAJOR_VERSION

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

# Copy configuration files
COPY ./config/php/*.ini /usr/local/etc/php/conf.d/
COPY ./config/php/${ENV}/*.ini /usr/local/etc/php/conf.d/
COPY ./config/php${PHP_MAJOR_VERSION}/*.ini /usr/local/etc/php/conf.d/
COPY ./config/imagemagick/* /etc/ImageMagick-6/
COPY ./config/frankenphp/* /etc/frankenphp/


# Stage 2: build final image
#FROM --platform=linux/${ARCH} dunglas/frankenphp:1.5-php${PHP_MAJOR_VERSION}-bookworm AS final
#ARG ENV
#ARG SERVER_NAME
#
### Install actual dependencies
#RUN apt install \
#    libzip4 libicu72 libldap-common \
#    imagemagick \
#    c-client \
#    odbcinst1debian2 \
#    bind-tools
#
## Copy previously built dependencies
## (SQL Server client and config, built and installed PHP extensions)
#COPY --from=build /etc/odbc.ini      /etc/odbc.ini
#COPY --from=build /etc/odbcinst.ini  /etc/odbcinst.ini
#COPY --from=build /opt/microsoft     /opt/microsoft
##COPY --from=build /opt/mssql-tools18 /opt/mssql-tools18
### TODO use `php -r "echo ini_get('extension_dir')`
#COPY --from=build /usr/local/lib/php/extensions/no-debug-zts-20220829 /usr/local/lib/php/extensions/no-debug-zts-20220829
#COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Write config

EXPOSE 80 443

WORKDIR /usr/share/schoolbox
