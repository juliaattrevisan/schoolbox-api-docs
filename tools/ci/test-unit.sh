#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "operating in dir: `pwd`";

if [ -z $1 ] || [ -z $2 ];
then
	echo "please provide two args: (vendor tarball) (vendor dev tarball)";
	exit 1;
fi

echo $initialdir " | " $instdir;

echo "Unpacking vendor dev build";
tar xzf ../$2;

echo "Unpacking vendor php build";
cd $initialdir;
tar xzf $1;
cd $instdir;

echo "Validating checksums";
checksum=`sha1sum composer.lock`;
if [ "$checksum" != "`cat ../vendor/composer.lock.checksum`" ];
then
	echo "Vendor checksum does not match. Expected '$checksum' Got: '`cat ../vendor/composer.lock.checksum`'";
	exit 1;
fi
if [ "$checksum" != "`cat vendor/composer.lock.checksum`" ];
then
	echo "Vendor dev checksum does not match. Expected '$checksum' Got: '`cat vendor/composer.lock.checksum`'";
	exit 1;
fi

echo "Preparing conf.php"
cp -f www/conf/tarball.conf.php www/conf/conf.php;

echo "Running php unit"
vendor/bin/paratest -p 8 --configuration phpunit.xml --testsuite default --coverage-clover clover.xml
unitTestResult=$?;
#phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --configuration phpunit.xml --testsuite default --coverage-clover clover.xml
#unitTestResult=$?;

echo "Uploading results to Code Climate"
export CC_TEST_REPORTER_ID=baf2d1f70fd896aed53229eb8da1f700d250bead944063cfdb190a9d40eef3cd;
curl -L https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter;
chmod +x ./cc-test-reporter;
./cc-test-reporter after-build -t clover --exit-code $unitTestResult;
