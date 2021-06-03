#!/bin/bash

## After the test is run we need to shut down the services
service php7.1-fpm stop
echo 'stopped php'
service nginx stop
echo 'stopped nginx'
service redis-server stop
echo 'stopped redis-server'
service mysql stop
echo 'stopped mysql'
