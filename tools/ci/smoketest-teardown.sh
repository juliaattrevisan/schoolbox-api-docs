#!/bin/bash

## After the test is run we need to shut down the services
PHP_VERSION=$1

## A case should be added with every supported PHP version
if [ $PHP_VERSION == 'php7.4' ]
then
  PHP_SERVICE="php7.4-fpm"
else
  echo "Unsupported PHP version: ${PHP_VERSION}"
  exit 1
fi

systemctl stop $PHP_SERVICE
echo 'stopped php'
systemctl stop nginx
echo 'stopped nginx'
systemctl stop redis-server
echo 'stopped redis-server'
systemctl stop mysql
echo 'stopped mysql'
