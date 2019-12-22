#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "Operating in dir: `pwd`";

if [ -f tools/ci/php_version_whitelist.txt ];
then
    echo "Checking PHP version";
    phpver=`php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1-2`;
    if [ `grep $phpver tools/ci/php_version_whitelist.txt | wc -l` -eq 0 ];
    then
        echo "PHP Version '$phpver' is not valid for this test";
        exit 0;
    fi
fi

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
	if [ $? -ne 0 ];
	then
		echo "Composer build of vendor php failed";
		exit 1;
	fi
fi

echo "Running phpstan checks"

vendor/phpstan/phpstan/phpstan analyze

phpstanResult=$?

if [ $phpstanResult -ne 0 ];
then
	echo "PHP Stan checks found issues.";
	exit 1;
fi
