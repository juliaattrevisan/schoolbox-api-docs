# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev
ARG ARCH=amd64

FROM --platform=linux/${ARCH} alaress/php82-fpm-alpine
