#!/bin/bash

./schoolbox-qa/tools/ci/smoketest-setup.sh
echo "smoke tests setup"
cd /vagrant
echo "cd into vagrant"
./vendor/bin/codecept run smoketests

smokeTestResult=$?;

service php7.1-fpm stop
echo 'stopped php'
service nginx stop
echo 'stopped nginx'
service redis-server stop
echo 'stopped redis-server'
service mysql stop
echo 'stopped mysql'

if [ $smokeTestResult -ne 0 ];
then
	echo "Error detected in the smoke test";
	exit 1;
fi

exit 0