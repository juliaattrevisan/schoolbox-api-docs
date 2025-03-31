# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Deployable dockerfile; assumes that:
# * an image from `Dockerfile` has been built, and is named `schoolbox-php-fpm`
# * this is run from the base dir of the `schoolbox` project
#
# It may be built with the commands:
# docker build -t schoolbox-php-fpm docker/php-fpm/
# docker build -t schoolbox-php-fpm-deploy -f docker/php-fpm/deploymentImage.Dockerfile .
FROM schoolbox-php-fpm

WORKDIR /usr/share/schoolbox

# Copy necessary files and folders only
COPY ./conf          /usr/share/schoolbox/conf
COPY ./dav           /usr/share/schoolbox/dav
COPY ./library       /usr/share/schoolbox/library
COPY ./packages      /usr/share/schoolbox/packages
COPY ./sql           /usr/share/schoolbox/sql
COPY ./templates     /usr/share/schoolbox/templates
COPY ./tools         /usr/share/schoolbox/tools
COPY ./updates       /usr/share/schoolbox/updates
COPY ./www           /usr/share/schoolbox/www
COPY ./composer.json /usr/share/schoolbox
COPY ./composer.lock /usr/share/schoolbox

# Composer build
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN if [[ " prod production " =~ " $ENV " ]] ; then composer install --no-dev ; else composer install ; fi
