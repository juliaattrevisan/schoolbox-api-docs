#!/bin/bash

PHP_VERSION=$1

if [ -z $PHP_VERSION ]
then
	echo "No PHP version set, do not run";
	exit 1;
fi

## Smoke test runs a setup script first
./schoolbox-develop/tools/ci/smoketest-setup.sh
echo "smoke tests setup"
cd /vagrant
echo "cd into vagrant"
./vendor/bin/codecept run smoketests

smokeTestResult=$?;

## After the test is run we need to shut down the services
./schoolbox-develop/tools/ci/smoketest-teardown.sh

## Then we need to close off our test with a return value, just to ensure the script closes properly.
if [ $smokeTestResult -ne 0 ];
then
	echo "Error detected in the smoke test";
	exit 1;
fi

exit 0
