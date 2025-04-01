# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev


# Stage 1: build dependencies
FROM php:8.2-alpine AS build
ARG ENV

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

# SQL Server ODBC driver install script:
# copied from https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server,
# adapted for sh shell and Dockerfile.
# (Moved into Dockerfile so that the script is available regardless of context)
RUN <<EOT
# SQL Server ODBC driver install script:
# copied from https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server,
# adapted for sh shell.

# Detect architecture, exit if unsupported
case $(uname -m) in
    x86_64)   architecture="amd64" ;;
    arm64)   architecture="arm64" ;;
    *) architecture="unsupported" ;;
esac
if [ "unsupported" = "$architecture" ];
then
    echo "Alpine architecture $(uname -m) is not currently supported.";
    exit;
fi

# Download the desired package(s)
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_$architecture.apk
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_$architecture.apk

# Verify signature
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_$architecture.sig
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_$architecture.sig

curl https://packages.microsoft.com/keys/microsoft.asc  | gpg --import -
gpg --verify msodbcsql18_18.4.1.1-1_$architecture.sig msodbcsql18_18.4.1.1-1_$architecture.apk
gpg --verify mssql-tools18_18.4.1.1-1_$architecture.sig mssql-tools18_18.4.1.1-1_$architecture.apk

# Install the package(s)
apk add --allow-untrusted msodbcsql18_18.4.1.1-1_$architecture.apk
apk add --allow-untrusted mssql-tools18_18.4.1.1-1_$architecture.apk
EOT

# Stage 2: build final image
FROM php:8.2-fpm-alpine AS final
ARG ENV

# Install actual dependencies
RUN apk add libzip icu-libs libldap \
            imagemagick imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
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

