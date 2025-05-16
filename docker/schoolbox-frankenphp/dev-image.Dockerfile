# FrankenPHP Schoolbox image

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev
ARG ARCH=amd64


# Stage 1: base off of just-built image php82-frankenphp
FROM --platform=linux/${ARCH} php82-frankenphp


# Stage 2: build final image
#FROM --platform=linux/${ARCH} dunglas/frankenphp:1.5-php8.2-bookworm AS final
#ARG ENV
#ARG SERVER_NAME
#
### Install actual dependencies
#RUN apt install \
#    libzip4 libicu72 libldap-common \
#    imagemagick \
#    c-client \
#    odbcinst1debian2 \
#    bind-tools
#
## Copy previously built dependencies
## (SQL Server client and config, built and installed PHP extensions)
#COPY --from=build /etc/odbc.ini      /etc/odbc.ini
#COPY --from=build /etc/odbcinst.ini  /etc/odbcinst.ini
#COPY --from=build /opt/microsoft     /opt/microsoft
##COPY --from=build /opt/mssql-tools18 /opt/mssql-tools18
### TODO use `php -r "echo ini_get('extension_dir')`
#COPY --from=build /usr/local/lib/php/extensions/no-debug-zts-20220829 /usr/local/lib/php/extensions/no-debug-zts-20220829
#COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Write config
COPY ./config/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443

WORKDIR /usr/share/schoolbox
