#!/bin/bash

## After the test is run we need to shut down the services
PHP_SERVICE="php7.4-fpm"

service $PHP_SERVICE stop
echo 'stopped php'
service nginx stop
echo 'stopped nginx'
service redis-server stop
echo 'stopped redis-server'
service mariadb stop
echo 'stopped mariadb'
