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

service $PHP_SERVICE stop
echo 'stopped php'
service nginx stop
echo 'stopped nginx'
service redis-server stop
echo 'stopped redis-server'
service mysql stop
echo 'stopped mysql'
