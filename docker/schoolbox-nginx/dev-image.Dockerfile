# Nginx+PHP Schoolbox image

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG ARCH=amd64


# Stage 1: base off of alaress/php82-alpine
# Note: this is a private repository, so need to set an alternate when building
# from a pull request.
FROM --platform=linux/${ARCH} alaress/php82-alpine AS build


# Stage 2: build final image
FROM --platform=linux/${ARCH} shinsenter/php:8.2-fpm-nginx-alpine AS final
ARG ENV

## Install actual dependencies
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
## TODO use `php -r "echo ini_get('extension_dir')`
COPY --from=build /usr/local/lib/php/extensions/no-debug-non-zts-20220829 /usr/local/lib/php/extensions/no-debug-non-zts-20220829
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

EXPOSE 80 443

WORKDIR /usr/share/schoolbox
