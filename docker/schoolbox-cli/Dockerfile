ARG ARCH=amd64

FROM --platform=linux/${ARCH} php82-bookworm

ARG ARCH
ARG NVM_VERSION='0.40.3'
ARG AWS_CLI_VERSION='2.27.55'
ARG NODE_VERSION=20

# Install Intellischool CLI client, as per instructions on
# https://docs.intellischool.co/guides/cli-quickstart#install-debian-ubuntu:
# since keys are publicly accessible on that page, I'm not counting them as
# private information.
# Also, install tools required by cron tasks and CLI:
# * git, mariadb client, ffmpeg, imagemagick, sudo
# dev also requires:
# * aws, zip (for extracting AWS CLI), pv (for tracking load of dataset)
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
    git \
    mariadb-client \
    ffmpeg \
    imagemagick \
    sudo

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
    # AWS CLI install
    apt-get install -y zip pv wget
    case "$ARCH" in
      arm64)
        export AWS_CLI_ARCH='aarch64'
        ;;
      *)
        export AWS_CLI_ARCH='x86_64'
        ;;
    esac
    curl "https://awscli.amazonaws.com/awscli-exe-linux-${AWS_CLI_ARCH}-${AWS_CLI_VERSION}.zip" -o "awscliv2.zip"
    unzip awscliv2.zip
    ./aws/install
    rm awscliv2.zip
    rm -rf ./aws

    # NPM install
    curl "https://raw.githubusercontent.com/nvm-sh/nvm/v${NVM_VERSION}/install.sh" -o nvm-install
    chmod +x nvm-install
    bash -c "./nvm-install && source /root/.nvm/nvm.sh && nvm install ${NODE_VERSION}"
    ;;
esac
EOF

# Install Composer (dev only?)
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Cleanup
RUN \
  apt-get remove -y gnupg && \
  apt-get autoremove -y && \
  apt-get clean -y

WORKDIR /usr/share/schoolbox
