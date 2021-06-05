#!/bin/bash

## After the test is run we need to shut down the services
PHP_SERVICE="php7.4-fpm"

systemctl stop $PHP_SERVICE
echo 'stopped php'
systemctl stop nginx
echo 'stopped nginx'
systemctl stop redis-server
echo 'stopped redis-server'
systemctl stop mysql
echo 'stopped mysql'
