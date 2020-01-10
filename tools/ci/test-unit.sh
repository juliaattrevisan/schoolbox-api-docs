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
sudo phpenmod -s cli xdebug
echo "Running php unit"
php -e vendor/bin/paratest -p 4 --configuration phpunit.xml --testsuite default; # --coverage-clover clover.xml
unitTestResult=$?;
sudo phpdismod -s cli xdebug

if [ $unitTestResult -ne 0 ];
then
	echo "PHP Unit tests failed";
	exit 1;
fi
