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
