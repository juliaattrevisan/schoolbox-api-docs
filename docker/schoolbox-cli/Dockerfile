ARG ARCH=amd64

FROM --platform=linux/${ARCH} php82-bookworm

# Install Intellischool CLI client, as per instructions on
# https://docs.intellischool.co/guides/cli-quickstart#install-debian-ubuntu:
# since keys are publicly accessible on that page, I'm not counting them as
# private information.
# Also, install tools required by cron tasks and CLI:
# git, mariadb client, ffmpeg, imagemagick, aws
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
    zip && \
  apt-get remove -y gnupg && \
  apt-get autoremove -y && \
  apt-get clean -y

# Install AWS CLI
RUN \
   curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" && \
   unzip awscliv2.zip && \
   sudo ./aws/install

WORKDIR /usr/share/schoolbox
