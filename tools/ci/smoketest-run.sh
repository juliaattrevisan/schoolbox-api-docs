#!/bin/bash

PHP_VERSION=$1

if [ -z $PHP_VERSION ]
then
	echo "No PHP version set, do not run";
	exit 1;
fi

filepath=`readlink -f $0`;
fullpath=${filepath%/*}

initialdir=`pwd`;

## Smoke test runs a setup script first
"$fullpath"/smoketest-setup.sh
echo "smoke tests setup"
cd /vagrant
echo "cd into vagrant"
INSTANCE_ID=smoketest ./vendor/bin/codecept run smoketests

smokeTestResult=$?;

## After the test is run we need to shut down the services
cd $initialdir;
"$fullpath"/smoketest-teardown.sh

## Then we need to close off our test with a return value, just to ensure the script closes properly.
if [ $smokeTestResult -ne 0 ];
then
	echo "Error detected in the smoke test";
	exit 1;
fi

exit 0
