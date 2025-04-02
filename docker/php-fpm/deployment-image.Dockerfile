# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

FROM alaress/php82-fpm-alpine

WORKDIR /usr/share/schoolbox

# Copy all Schoolbox code from local filesystem to container.
# Consider using ONBUILD in the base container for this?
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
