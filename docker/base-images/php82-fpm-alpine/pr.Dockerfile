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
