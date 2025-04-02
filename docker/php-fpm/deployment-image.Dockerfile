# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

## Deployable dockerfile; assumes that the following base images have been built,
## with the given names:
## * ../artifact-images/schoolbox-composer/Dockerfile => alaress/schoolbox-composer
## * ../artifact-images/schoolbox-npm/Dockerfile      => alaress/schoolbox-npm
## The build platform doesn't matter, as the artifacts are platform-independent.
## In fact, the Github Actions build process probably won't even use the artifact
## images.
#FROM --platform=$BUILDPLATFORM alaress/schoolbox-composer AS composer
#FROM --platform=$BUILDPLATFORM alaress/schoolbox-npm      AS npm

FROM alaress/php82-fpm-alpine

WORKDIR /usr/share/schoolbox

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

#COPY --from=composer /usr/share/schoolbox/vendor               /usr/share/schoolbox/vendor
#COPY --from=npm      /usr/share/schoolbox/www/static/js/bundle /usr/share/schoolbox/www/static/js/bundle
