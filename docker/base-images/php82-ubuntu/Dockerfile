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
ARG ARCH=amd64

FROM --platform=linux/${ARCH} ubuntu:jammy

# Ensure ENV is accessible by this build step:
# otherwise, xDebug will be installed below.
ARG ENV
ENV DEBIAN_FRONTEND noninteractive

# Setup ondrej/php repo
RUN \
  apt-get update && \
  apt-get install -y language-pack-en-base software-properties-common && \
  export LC_ALL=en_US.UTF-8 && \
  export LANG=en_US.UTF-8 && \
  add-apt-repository -y ppa:ondrej/php && \
  apt-get update

# Install PHP dependencies, as well as likely requirements, as per
# https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2584313884/Services+within+Schoolbox#Execution-of-shell-commands
RUN \
  apt-get install -y \
    php8.2-apcu \
    php8.2-cli \
    php8.2-bcmath \
    php8.2-common \
    php8.2-curl \
    php8.2-dev \
    php8.2-excimer \
    php8.2-gd \
    php8.2-intl \
    php8.2-imap \
    php8.2-ldap \
    php8.2-mbstring \
    php8.2-mysql \
    php8.2-opcache \
    php8.2-readline \
    php8.2-xml \
    php8.2-zip \
    php8.2-imagick \
    php8.2-redis \
    git \
    mariadb-client \
    ffmpeg \
    unixodbc-dev \
    imagemagick

# Install XDebug on non-production:
# for some reason, `apt-get install php8.2-xdebug` doesn't install it
RUN <<EOF
case "$ENV" in
  prod)
    ;;
  production)
    ;;
  *)
    pecl install xdebug
    echo "zend_extension=xdebug" > /etc/php/8.2/mods-available/xdebug.ini
    phpenmod xdebug
    ;;
esac
EOF

# Cleanup
RUN \
  apt-get autoremove -y && \
  apt-get clean -y && \
  rm -rf /var/lib/apt/lists/* && \
  update-alternatives --set php /usr/bin/php8.2

# Install ODBC driver
COPY ./opt/bin/install-odbc-driver.sh /opt/bin/install-odbc-driver.sh
RUN /opt/bin/install-odbc-driver.sh


# Install pdo_sqlsrv
RUN <<EOF
pecl install pdo_sqlsrv
echo "extension=pdo_sqlsrv.so" > /etc/php/8.2/mods-available/pdo_sqlsrv.ini
phpenmod pdo_sqlsrv
EOF

# Copy configuration files
COPY ./config/* /etc/php/8.2/cli/conf.d/

# schoolbox-instance-registry and schoolbox-read-instances:
# copy from puppet-alaress as-is for now
COPY ./usr/local/bin/* /usr/local/bin/
