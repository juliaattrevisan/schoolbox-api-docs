# FrankenPHP Schoolbox image

ARG ENV=dev
ARG PHP_MAJOR_VERSION=8.4
ARG PRIVATE_CONTAINER_REGISTRY=476058706527.dkr.ecr.ap-southeast-2.amazonaws.com
ARG IMAGE=${PRIVATE_CONTAINER_REGISTRY}/frankenphp-base
ARG TAG=latest-php${PHP_MAJOR_VERSION}-${ENV}
FROM ${IMAGE}:${TAG}

ARG ENV
ARG PHP_MAJOR_VERSION

# Copy configuration files
COPY ./config/php/*.ini /usr/local/etc/php/conf.d/
COPY ./config/php/${ENV}/*.ini /usr/local/etc/php/conf.d/
COPY ./config/php${PHP_MAJOR_VERSION}/*.ini /usr/local/etc/php/conf.d/
COPY ./config/imagemagick/* /etc/ImageMagick-6/
COPY ./config/frankenphp/* /etc/frankenphp/
