ARG ENV=prod
ARG PHP_MAJOR_VERSION=8.4
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}
ARG BASE_PHP_IMAGE=${IMAGE}:${TAG}

FROM ${BASE_PHP_IMAGE}

# Copy code from code context to image
COPY --from=code ./vendor    /usr/share/schoolbox/vendor
COPY --from=code ./conf      /usr/share/schoolbox/conf
COPY --from=code ./sql       /usr/share/schoolbox/sql
COPY --from=code ./updates   /usr/share/schoolbox/updates
COPY --from=code ./packages  /usr/share/schoolbox/packages
COPY --from=code ./www       /usr/share/schoolbox/www
COPY --from=code ./templates /usr/share/schoolbox/templates
COPY --from=code ./tools     /usr/share/schoolbox/tools
COPY --from=code ./library   /usr/share/schoolbox/library

# Run as non-root user, because FrankenPHP needs to write to a shared folder
USER www-data
