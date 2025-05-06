# Nginx+PHP Schoolbox image

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG ARCH=amd64


# Stage 1: base off of just-built image php82-alpine
FROM --platform=linux/${ARCH} php82-alpine AS build


# Stage 2: build final image
FROM --platform=linux/${ARCH} shinsenter/php:8.2-fpm-nginx-alpine AS final
ARG ENV
ARG SERVER_NAME

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

# Write config
RUN rm /etc/nginx/sites-enabled/*.conf
COPY config/schoolbox.conf /etc/nginx/sites-enabled/schoolbox.conf
RUN mkdir -p /etc/nginx/sites-enabled/schoolbox.d/ && \
    echo "server_name ${SERVER_NAME};" > /etc/nginx/sites-enabled/schoolbox.d/server_name.conf

EXPOSE 80 443

WORKDIR /usr/share/schoolbox

# Only current difference between deploy and dev builds is to copy all Schoolbox
# code from code context to image.
COPY --from=code ./conf      /usr/share/schoolbox/conf
COPY --from=code ./dav       /usr/share/schoolbox/dav
COPY --from=code ./library   /usr/share/schoolbox/library
COPY --from=code ./packages  /usr/share/schoolbox/packages
COPY --from=code ./sql       /usr/share/schoolbox/sql
COPY --from=code ./templates /usr/share/schoolbox/templates
COPY --from=code ./tools     /usr/share/schoolbox/tools
COPY --from=code ./updates   /usr/share/schoolbox/updates
COPY --from=code ./vendor    /usr/share/schoolbox/vendor
COPY --from=code ./www       /usr/share/schoolbox/www
