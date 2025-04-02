# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Build step for Composer's `vendor` folder.
FROM php:8.2-alpine
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

WORKDIR /usr/share/schoolbox

# Include build requirements, as well as any files which are in the `autoload`
# section of `composer.json`: these need to be known by composer so that their
# classmap may be built.
COPY ./conf          /usr/share/schoolbox/conf
COPY ./library       /usr/share/schoolbox/library
COPY ./composer.json /usr/share/schoolbox
COPY ./composer.lock /usr/share/schoolbox

# Ignore platform requirements:
# the base PHP image doesn't include all of the extensions in composer.json.
RUN if [[ " prod production " =~ " $ENV " ]] ; \
    then composer install -a --ignore-platform-reqs --no-dev ; \
    else composer install -a --ignore-platform-reqs ; fi
