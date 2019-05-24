#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "operating in dir: `pwd`";

branch='master';
if [ ! -z $1 ];
then
	branch="$1";
fi

echo "Branch: $branch";

echo "Building Vendor PHP";
tools/composer.sh --no-dev
if [ $? != 0 ]
then
    echo "Failed to build vendor PHP";
    exit -1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/built-artifacts;
if [ $? != 0 ]
then
    echo "Failed creating artifact directory";
    exit -1;
fi

echo "Creating artifact checksum";
sha1sum composer.lock > vendor/composer.lock.checksum;
if [ $? != 0 ]
then
    echo "Failed creating composer.lock checksum";
    exit -1;
fi

echo "Creating artifact";
tar czf $initialdir/built-artifacts/"$branch"-vendor-php.tar.gz vendor;
if [ $? != 0 ]
then
    echo "Failed zipping vendor into artifact file";
    exit -1;
fi

echo "Building Vendor (dev) PHP";
tools/composer.sh
if [ $? != 0 ]
then
    echo "Failed building dev Vendor";
    exit -1;
fi

echo "Creating (dev) artifact checksum";
sha1sum composer.lock > vendor/composer.lock.checksum;
if [ $? != 0 ]
then
    echo "Failed creating composer.lock checksum";
    exit -1;
fi

echo "Creating (dev) artifact";
tar czf $initialdir/built-artifacts/"$branch"-vendor-php-dev.tar.gz vendor;
if [ $? != 0 ]
then
    echo "Failed zipping vendor into artifact file";
    exit -1;
fi

echo "Artifact List: `readlink -f $initialdir/built-artifacts/`";
ls -lhR $initialdir/built-artifacts/;
