ARG ARCH=amd64

FROM --platform=linux/${ARCH} php82-bookworm

# Install Intellischool CLI client, as per instructions on
# https://docs.intellischool.co/guides/cli-quickstart#install-debian-ubuntu:
# since keys are publicly accessible on that page, I'm not counting them as
# private information.
# Also, install tools required by cron tasks and CLI:
# * git, mariadb client, ffmpeg, imagemagick
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
    zip \
    pv && \
  apt-get remove -y gnupg && \
  apt-get autoremove -y && \
  apt-get clean -y

# Install AWS CLI (dev only)
RUN \
   curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" && \
   unzip awscliv2.zip && \
   ./aws/install

# Install Composer (dev only)
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /usr/share/schoolbox
