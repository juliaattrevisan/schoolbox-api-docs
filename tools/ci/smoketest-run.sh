#!/bin/bash

## Smoke test runs a setup script first
./schoolbox-develop/tools/ci/smoketest-setup.sh
echo "smoke tests setup"
cd /vagrant
echo "cd into vagrant"
./vendor/bin/codecept run smoketests

smokeTestResult=$?;

## After the test is run we need to shut down the services
service php7.1-fpm stop
echo 'stopped php'
service nginx stop
echo 'stopped nginx'
service redis-server stop
echo 'stopped redis-server'
service mysql stop
echo 'stopped mysql'

## Then we need to close off our test with a return value, just to ensure the script closes properly.
if [ $smokeTestResult -ne 0 ];
then
	echo "Error detected in the smoke test";
	exit 1;
fi

exit 0
