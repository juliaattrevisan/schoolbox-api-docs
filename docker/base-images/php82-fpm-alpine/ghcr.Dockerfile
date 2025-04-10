# PHP-FPM Schoolbox image, for GHCR

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev


# Stage 1: base off of GHCR alaress/php82-alpine
FROM ghcr.io/alaress/php82-alpine AS build


# Stage 2: build final image
FROM shinsenter/php:8.2-fpm-alpine AS final
ARG ENV

## Install actual dependencies
RUN apk add libzip icu-libs libldap \
            imagemagick imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
            c-client \
            unixodbc \
            bind-tools

# Dependencies will be copied from a separately built base image.
# (SQL Server client and config, built and installed PHP extensions)
COPY --from=build /etc/odbc.ini      /etc/odbc.ini
COPY --from=build /etc/odbcinst.ini  /etc/odbcinst.ini
COPY --from=build /opt/microsoft     /opt/microsoft
COPY --from=build /opt/mssql-tools18 /opt/mssql-tools18
## TODO use `php -r "echo ini_get('extension_dir')`
COPY --from=build /usr/local/lib/php/extensions/no-debug-non-zts-20220829 /usr/local/lib/php/extensions/no-debug-non-zts-20220829
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
