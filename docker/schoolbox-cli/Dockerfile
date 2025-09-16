ARG ARCH=amd64
ARG ENV=dev
ARG PHP_MAJOR_VERSION=8.4
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}
ARG USER_ID
ARG GROUP_ID

FROM ${IMAGE}:${TAG}

ARG ARCH
ARG NVM_VERSION='0.40.3'
ARG AWS_CLI_VERSION='2.27.55'
ARG NODE_VERSION=20
ARG USER_ID
ARG GROUP_ID

# Install Intellischool CLI client, as per instructions on
# https://docs.intellischool.co/guides/cli-quickstart#install-debian-ubuntu:
# since keys are publicly accessible on that page, I'm not counting them as
# private information.
# Also, install tools required by cron tasks and CLI:
# * cron itself, git, mariadb client, ffmpeg, imagemagick, sudo, jq
RUN \
  apt-get update && \
  apt-get install -y gnupg && \
  echo "deb https://cli.intellischool.net/channels/stable/apt/ /" > /etc/apt/sources.list.d/intelliscl.list && \
  apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 88C06AD00AA2BB47 && \
  rm /etc/apt/trusted.gpg.d/intellischool.gpg 2> /dev/null || echo > /dev/null && \
  apt-key export 0AA2BB47 | gpg --dearmour -o /etc/apt/trusted.gpg.d/intellischool.gpg && \
  apt-get update && \
  apt-get install -y \
    intelliscl \
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
    curl "https://raw.githubusercontent.com/nvm-sh/nvm/v${NVM_VERSION}/install.sh" -o nvm-install
    chmod +x nvm-install
    mkdir -p /opt/nvm
    NVM_DIR=/opt/nvm bash -c "./nvm-install && source /opt/nvm/nvm.sh && nvm install ${NODE_VERSION}"
    ;;
esac
EOF

# Copy NVM profile and sudoers conf
COPY ./config/profile.d/* /etc/profile.d/
COPY ./config/sudoers.d/* /etc/sudoers.d/

# Install Composer (dev only?)
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Cleanup
RUN \
  apt-get remove -y gnupg && \
  apt-get autoremove -y && \
  apt-get clean -y

WORKDIR /usr/share/schoolbox

# Create users and groups, and set default user
RUN <<EOF
addgroup --gid ${GROUP_ID} schoolboxsuper
useradd -m -g schoolboxsuper -G adm,sudo,www-data -s /bin/bash -u ${USER_ID} schoolboxsuper
EOF

USER schoolboxsuper
