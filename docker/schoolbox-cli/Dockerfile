ARG PHP_MAJOR_VERSION=8.4
ARG ENV=dev
ARG BASE_BRANCH=master
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}-${BASE_BRANCH}
ARG BASE_PHP_IMAGE=${IMAGE}:${TAG}

FROM ${BASE_PHP_IMAGE}

ARG NVM_VERSION='0.40.3'
ARG AWS_CLI_VERSION='2.27.55'
ARG NODE_VERSION=24

# Install tools required by cron tasks and CLI:
# * cron itself, git, mariadb client, ffmpeg, imagemagick, sudo, jq
RUN \
  apt-get update && \
  apt-get install -y \
    cron \
    git \
    mariadb-client \
    ffmpeg \
    imagemagick \
    sudo \
    jq

# Install added requirements for non-production instances:
# * AWS CLI
# * zip (for extracting AWS CLI)
# * pv (for tracking load of dataset)
# * wget
# * npm
RUN <<EOF
case "$ENV" in
  prod | production)
    ;;
  *)
    # Supporting tools install
    apt-get install -y zip pv wget
    ;;
esac
EOF

RUN <<EOF
case "$ENV" in
  prod | production)
    ;;
  *)
    # AWS CLI install for current system architecture
    curl "https://awscli.amazonaws.com/awscli-exe-linux-$(uname -m)-${AWS_CLI_VERSION}.zip" -f -o "awscliv2.zip" || exit 1
    unzip awscliv2.zip
    ./aws/install
    rm awscliv2.zip
    rm -rf ./aws
    ;;
esac
EOF

RUN <<EOF
case "$ENV" in
  prod | production)
    ;;
  *)
    # NPM install for all users into /opt/nvm
    curl "https://raw.githubusercontent.com/nvm-sh/nvm/v${NVM_VERSION}/install.sh" -f -o nvm-install || exit 1
    chmod +x nvm-install
    mkdir -p /opt/nvm
    NVM_DIR=/opt/nvm bash -c "./nvm-install && source /opt/nvm/nvm.sh && nvm install ${NODE_VERSION}"
    ;;
esac
EOF

# Install Intellischool CLI client, as per instructions on
# https://docs.intellischool.co/guides/cli-quickstart#install-debian-ubuntu:
# need to install from a downloaded tar file, because it can't be installed
# under Trixie yet.
RUN <<EOF
case "$(uname -m)" in
  x86_64 )
    curl https://cli.intellischool.net/channels/stable/intelliscl-linux-x64.tar.gz -f -o "intelliscl.tar.gz" || exit 1
    ;;
  aarch64 )
    curl https://cli.intellischool.net/channels/stable/intelliscl-linux-arm64.tar.gz -f -o "intelliscl.tar.gz" || exit 1
    ;;
  * )
    echo "Unknown architecture: not installing Intellischool CLI client"
    exit
    ;;
esac
tar -C /opt/ -xzf intelliscl.tar.gz
rm intelliscl.tar.gz
EOF

# Copy various configs
COPY ./config/profile.d/* /etc/profile.d/
COPY ./config/sudoers.d/* /etc/sudoers.d/
COPY ./config/mariadb.conf.d/* /etc/mysql/mariadb.conf.d/
COPY ./config/php/* /usr/local/etc/php/conf.d/

# Install Composer (dev only?)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cleanup
RUN \
  apt-get remove -y gnupg && \
  apt-get autoremove -y && \
  apt-get clean -y

WORKDIR /usr/share/schoolbox

# Create /var/log/schoolbox folder, writable by www-data and its group
# (to which schoolboxsuper belongs)
RUN <<EOF
mkdir -m 0775 /var/log/schoolbox
chown www-data:www-data /var/log/schoolbox
EOF
