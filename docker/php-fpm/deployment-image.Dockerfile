# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Deployable dockerfile; assumes that:
# * the following base images have been built, with the given names:
#   * ./Dockerfile                     => schoolbox-php-fpm
#   * ../schoolbox-composer/Dockerfile => schoolbox-composer
#   * ../schoolbox-npm/Dockerfile      => schoolbox-npm
# * this is run from the base dir of the `schoolbox` project
#
# It may be built with the commands:
# docker build -t schoolbox-php-fpm        -f docker/schoolbox-fpm/Dockerfile            .
# docker build -t schoolbox-composer       -f docker/schoolbox-composer/Dockerfile       .
# docker build -t schoolbox-npm            -f docker/schoolbox-npm/Dockerfile            .
# docker build -t schoolbox-php-fpm-deploy -f docker/php-fpm/deployment-image.Dockerfile .
#
# It may then be tested with the command:
# docker compose -f docker/deploy.docker-compose.yaml

FROM schoolbox-php-fpm

WORKDIR /usr/share/schoolbox

COPY ./conf      /usr/share/schoolbox/conf
COPY ./dav       /usr/share/schoolbox/dav
COPY ./library   /usr/share/schoolbox/library
COPY ./packages  /usr/share/schoolbox/packages
COPY ./sql       /usr/share/schoolbox/sql
COPY ./templates /usr/share/schoolbox/templates
COPY ./tools     /usr/share/schoolbox/tools
COPY ./updates   /usr/share/schoolbox/updates
COPY ./www       /usr/share/schoolbox/www

COPY --from=schoolbox-composer /usr/share/schoolbox/vendor               /usr/share/schoolbox/vendor
COPY --from=schoolbox-npm      /usr/share/schoolbox/www/static/js/bundle /usr/share/schoolbox/www/static/js/bundle
