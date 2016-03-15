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

echo "Unpacking vendor php build";
tar xzf ../$1;

echo "Unpacking vendor dev build";
cd $initialdir;
tar xzf $2;
cd $instdir;

echo "Validating checksums";
checksum=`sha1sum composer.lock`;
if [ "$checksum" != "`cat vendor/composer.lock.checksum`" ];
then
	echo "Vendor checksum does not match";
	exit 1;
fi
if [ "$checksum" != "`cat ../vendor/composer.lock.checksum`" ];
then
	echo "Vendor dev checksum does not match";
	exit 1;
fi

echo "Preparing conf.php"
cp -f www/conf/tarball.conf.php www/conf/conf.php;

echo "Running php unit"
php ../vendor/bin/phpunit;
