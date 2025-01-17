#!/bin/bash

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

branch='master';
if [ ! -z $1 ];
then
	branch="$1";
fi

echo "Branch: $branch";

## Run the smoke test setup script to get the environment ready
## We pretty much need the same configuration, then just install playwright afterwards
"$fullpath"/smoketest-setup.sh $PHP_VERSION
echo "${TEST_SUITE} tests setup"

"$fullpath"/acceptancetest-setup.sh
echo "acceptancetest setup"

## Run tests
INSTANCE_ID=smoketest CI=true npx playwright test

testResult=$?;

echo "Slow web/image requests:"
cat /var/log/php-fpm.schoolbox.slow.log
cat /var/log/php-fpm.schoolbox-images.slow.log

## After the test is run we need to shut down the services
cd $initialdir;
"$fullpath"/smoketest-teardown.sh $PHP_VERSION

service rabbitmq-server stop

## Output Artifacts

echo "Creating artifact directory";
mkdir -vp $initialdir/built-artifacts;

echo "Creating artifact";
artifactsDir="/usr/share/schoolbox/test/playwright/_output/artifacts"
if [ -d "$artifactsDir" ]; then
    tar czf "$initialdir/built-artifacts/$branch-acceptancetest.tar.gz" "$artifactsDir";
else
    echo "No playwright artifacts found";
fi

echo "Artifact List: `readlink -f $initialdir/built-artifacts/`";
ls -lhR $initialdir/built-artifacts/;

## Then we need to close off our test with a return value, just to ensure the script closes properly.
if [ $testResult -ne 0 ];
then
  echo "Error detected in the ${TEST_SUITE} test";
  exit 1;
fi

exit 0
