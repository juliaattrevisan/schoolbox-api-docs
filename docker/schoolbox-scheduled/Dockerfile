ARG PHP_MAJOR_VERSION=8.4
ARG ENV=dev
ARG BASE_BRANCH=master
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}-${BASE_BRANCH}
ARG BASE_PHP_IMAGE=${IMAGE}:${TAG}

FROM ${BASE_PHP_IMAGE}

WORKDIR /usr/share/schoolbox

CMD php /usr/share/schoolbox/tools/mq/scheduled.php -vvv
