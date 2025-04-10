# Base PHP Schoolbox image: will be used as a base for these images:
# * PHP-FPM
# * Daemons
#
# Will be uploaded to Schoolbox's container repository, so that this need not be
# rebuilt every time Docker Compose is run.

# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies.
ARG ENV=dev

FROM shinsenter/php:8.2-alpine

# Install additional extensions
# (not in https://github.com/shinsenter/php?tab=readme-ov-file#pre-installed-php-extensions)
RUN phpaddmod \
    imagick \
    pdo_sqlsrv \
    excimer \
    curl \
    ldap \
    dom \
    sockets \
    imap

RUN if [[ ! " prod production " =~ " $ENV " ]] ; then phpaddmod xdebug ; fi

# Install ODBC driver
COPY ./bin/install-odbc-driver.sh /opt/bin/install-odbc-driver.sh
RUN /opt/bin/install-odbc-driver.sh

# Copy configuration files
COPY ./config/* /usr/local/etc/php/conf.d/
