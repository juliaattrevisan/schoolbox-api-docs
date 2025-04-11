# PHP-FPM Schoolbox image, for GHCR

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev

FROM shinsenter/php:8.2-fpm-alpine
ARG ENV

## Install actual dependencies
RUN apk add libzip icu-libs libldap \
            imagemagick imagemagick-heic imagemagick-raw imagemagick-svg imagemagick-tiff imagemagick-webp \
            c-client \
            unixodbc \
            bind-tools

# Dependencies will be copied from a separately built base image.
WORKDIR /
COPY --from=php82-alpine ./etc/ /etc/
COPY --from=php82-alpine ./opt/ /opt/
COPY --from=php82-alpine ./usr/ /usr/

WORKDIR /usr/share/schoolbox

# Copy all Schoolbox code from code context to image.
COPY ./conf      /usr/share/schoolbox/conf
COPY ./dav       /usr/share/schoolbox/dav
COPY ./library   /usr/share/schoolbox/library
COPY ./packages  /usr/share/schoolbox/packages
COPY ./sql       /usr/share/schoolbox/sql
COPY ./templates /usr/share/schoolbox/templates
COPY ./tools     /usr/share/schoolbox/tools
COPY ./updates   /usr/share/schoolbox/updates
COPY ./vendor    /usr/share/schoolbox/vendor
COPY ./www       /usr/share/schoolbox/www
