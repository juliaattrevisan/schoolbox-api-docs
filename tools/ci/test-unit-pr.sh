#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "Operating in dir: `pwd`";

if [ ! -z $1 ];
then
	echo "Unpacking vendor tarball: $1";
	cd $initialdir;
	tar xzf $1;
	cd $instdir;

	echo "Validating vendor checksums";
	checksum=`sha1sum composer.lock`;
	if [ "$checksum" != "`cat $initialdir/vendor/composer.lock.checksum`" ];
	then
		echo "Vendor checksum does not match, rebuilding vendor php from composer";
		tools/composer.sh;
		if [ $? -ne 0 ];
		then
			echo "Composer build of vendor php failed";
			exit 1;
		fi
	else
		echo "Vendor checksum matches, using tarball as vendor source";
		mv $initialdir/vendor $instdir/vendor;
	fi
else
	echo "No prebuilt vendor tarball supplied, rebuilding vendor php from composer";
	tools/composer.sh;
fi

echo "Preparing conf.php"
cp -f www/conf/tarball.conf.php www/conf/conf.php;

echo "Running php unit"
php vendor/bin/phpunit;
