# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Deployable dockerfile; assumes that the following base images have been built,
# with the given names:
# * ../artifact-images/schoolbox-composer/Dockerfile => schoolbox/composer
# * ../artifact-images/schoolbox-npm/Dockerfile      => schoolbox/npm
# The build platform doesn't matter, as the artifacts are platform-independent.
FROM --platform=$BUILDPLATFORM schoolbox/composer AS composer
FROM --platform=$BUILDPLATFORM schoolbox/npm      AS npm

FROM schoolbox/php82-fpm AS final

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

COPY --from=composer /usr/share/schoolbox/vendor               /usr/share/schoolbox/vendor
COPY --from=npm      /usr/share/schoolbox/www/static/js/bundle /usr/share/schoolbox/www/static/js/bundle
