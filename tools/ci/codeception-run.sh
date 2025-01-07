#!/bin/bash

TEST_SUITE=$1
case "$TEST_SUITE" in
    smoketests)
      TEST_ARGS="Smoketests"
      ;;
    api)
      TEST_ARGS="Api"
      ;;
    ci)
      TEST_ARGS="Smoketests,Api"
      ;;
    *)
      echo "{Smoketests|Api|Ci}"
      exit 1
esac


filepath=`readlink -f $0`;
fullpath=${filepath%/*}

initialdir=`pwd`;

## Is the PHP version valid?
if [ -f ${fullpath}/php_version_whitelist.txt ];
then
    echo "Checking PHP version";
    PHP_VERSION=`php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1-2`;
    if [ `grep $PHP_VERSION ${fullpath}/php_version_whitelist.txt | wc -l` -eq 0 ];
    then
        echo "PHP Version '$PHP_VERSION' is not valid for this test";
        exit 0;
    else
        echo "Running PHP Version: '$PHP_VERSION'";
    fi
else
    echo "No ${fullpath}/php_version_whitelist.txt exists: exiting";
    exit 1;
fi


## Smoke test runs a setup script first
"$fullpath"/smoketest-setup.sh $PHP_VERSION
echo "${TEST_SUITE} tests setup"
cd /usr/share/schoolbox
echo "cd into vagrant"
INSTANCE_ID=smoketest ./vendor/bin/codecept run $TEST_ARGS

testResult=$?;

echo "Slow web/image requests:"
cat /var/log/php-fpm.schoolbox.slow.log
cat /var/log/php-fpm.schoolbox-images.slow.log


## After the test is run we need to shut down the services
cd $initialdir;
"$fullpath"/smoketest-teardown.sh $PHP_VERSION

## Then we need to close off our test with a return value, just to ensure the script closes properly.
if [ $testResult -ne 0 ];
then
  echo "Error detected in the ${TEST_SUITE} test";
  exit 1;
fi

exit 0
