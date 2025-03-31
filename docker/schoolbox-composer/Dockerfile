# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Build step for Composer's `vendor` folder.
#
# docker build -t schoolbox-composer -f docker/schoolbox-composer/Dockerfile .
FROM php:8.2-alpine

WORKDIR /usr/share/schoolbox

COPY ./composer.json /usr/share/schoolbox
COPY ./composer.lock /usr/share/schoolbox
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

# Ignore platform requirements:
# the base PHP image doesn't include all of the extensions in composer.json.
RUN if [[ " prod production " =~ " $ENV " ]] ; \
    then composer install -a --ignore-platform-reqs --no-dev ; \
    else composer install -a --ignore-platform-reqs ; fi
RUN rm /usr/local/bin/composer
