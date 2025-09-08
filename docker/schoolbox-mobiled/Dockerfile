ARG ENV=dev
ARG PHP_MAJOR_VERSION=8.4
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}

FROM ${IMAGE}:${TAG}

WORKDIR /usr/share/schoolbox

CMD php /usr/share/schoolbox/tools/mobile/mobiled.php -vvv
