# For now: base this off of the base FrankenPHP image, but install Python deps
# as well.
ARG PHP_MAJOR_VERSION=8.4
ARG ENV=dev
ARG BASE_BRANCH=master
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}-${BASE_BRANCH}
ARG BASE_PHP_IMAGE=${IMAGE}:${TAG}

FROM ${BASE_PHP_IMAGE}

# Current toolsd Dockerfile
RUN groupadd user && useradd --create-home --home-dir /home/user -g user user
WORKDIR /home/user

RUN apt-get update && apt-get install -y python3 python3-pip && \
    pip install --break-system-packages redis~="7.2" && \
    pip install --break-system-packages celery~="5.6" && \
    pip install --break-system-packages flower~="2.0" && \
    pip install --break-system-packages jsonschema~="4.26" && \
    pip install --break-system-packages PyYAML~="6.0" && \
    pip install --break-system-packages Flask~="3.1" && \
    pip install --break-system-packages waitress~="3.0"

# Install Pylint in dev only
RUN apt-get update && \
    apt-get -y install pylint && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /usr/src/app

USER user
CMD ["src/main.py"]
