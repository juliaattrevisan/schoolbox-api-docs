#!/bin/bash

if [ -f tools/ci/php_version_whitelist.txt ];
then
    echo "Checking PHP version";
    phpver=`php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1-2`;
    if [ `grep $phpver tools/ci/php_version_whitelist.txt | wc -l` -eq 0 ];
    then
        echo "PHP Version '$phpver' is not valid for this test";
        exit 0;
    else
        echo "Running PHP Version: '$phpver'";
    fi
else
    echo "No tools/ci/php_version_whitelist.txt exists: exiting";
    exit 1;
fi

filepath=`readlink -f $0`;
fullpath=${filepath%/*}

initialdir=`pwd`;

## Smoke test runs a setup script first
"$fullpath"/smoketest-setup.sh phpver
echo "smoke tests setup"
cd /usr/share/schoolbox
echo "cd into vagrant"
INSTANCE_ID=smoketest ./vendor/bin/codecept run smoketests

smokeTestResult=$?;

echo "Slow web/image requests:"
cat /var/log/php-fpm.schoolbox.slow.log
cat /var/log/php-fpm.schoolbox-images.slow.log


## After the test is run we need to shut down the services
cd $initialdir;
"$fullpath"/smoketest-teardown.sh phpver

## Then we need to close off our test with a return value, just to ensure the script closes properly.
if [ $smokeTestResult -ne 0 ];
then
	echo "Error detected in the smoke test";
	exit 1;
fi

exit 0
