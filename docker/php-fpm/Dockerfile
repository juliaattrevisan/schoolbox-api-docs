# Stage 1: build dependencies
FROM php:8.2-alpine AS build

# Install dependencies for building PECL extensions
RUN apk --no-cache add ${PHPIZE_DEPS} && \
    apk add --update linux-headers && \
    apk add libzip-dev zip unzip libxml2-dev oniguruma-dev icu-dev curl-dev openldap-dev \
            imagemagick-dev imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
            imap-dev \
            unixodbc-dev gnupg \
            bind-tools

# Install PECL extensions
RUN pecl install redis xdebug imagick pdo_sqlsrv excimer

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
COPY bin/install-odbc-driver.sh /opt/bin/install-odbc-driver.sh
RUN /opt/bin/install-odbc-driver.sh


# Stage 2: build final image
FROM php:8.2-fpm-alpine AS final

# Install actual dependencies
RUN apk add libzip icu-libs libldap \
            imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
            c-client \
            unixodbc \
            bind-tools

# Copy previously built dependencies
# (SQL Server client and config, built and installed PHP extensions)
COPY --from=build /etc/odbc.ini      /etc/odbc.ini
COPY --from=build /etc/odbcinst.ini  /etc/odbcinst.ini
COPY --from=build /opt/microsoft     /opt/microsoft
COPY --from=build /opt/mssql-tools18 /opt/mssql-tools18
# TODO use `php -r "echo ini_get('extension_dir')`
COPY --from=build /usr/local/lib/php/extensions/no-debug-non-zts-20220829 /usr/local/lib/php/extensions/no-debug-non-zts-20220829

# Enable all built dependencies
RUN docker-php-ext-enable zip && \
    docker-php-ext-enable pcntl && \
    docker-php-ext-enable pdo && \
    docker-php-ext-enable pdo_mysql && \
    docker-php-ext-enable mbstring && \
    docker-php-ext-enable intl && \
    docker-php-ext-enable curl && \
    docker-php-ext-enable ldap && \
    docker-php-ext-enable dom && \
    docker-php-ext-enable sockets && \
    docker-php-ext-enable exif && \
    docker-php-ext-enable imap && \
    docker-php-ext-enable redis && \
    docker-php-ext-enable xdebug && \
    docker-php-ext-enable imagick && \
    docker-php-ext-enable pdo_sqlsrv && \
    docker-php-ext-enable excimer
