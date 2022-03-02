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
cd /usr/share/schoolbox
echo "cd into vagrant"
INSTANCE_ID=smoketest ./vendor/bin/codecept run smoketests

smokeTestResult=$?;

# TODO remove debug. Dump first 10 failures
echo "TODO. remove debug."
tree /var/log
cat /var/log/php-fpm/schoolbox_php_error.log
ls -la /usr/share/schoolbox/test/codeception/_output/*.fail.html | head -n 10
for file in $(find /usr/share/schoolbox/test/codeception/_output/ -name '*fail.html' | head -n 10); do cat "$file"; done

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
