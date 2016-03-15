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

echo "Unpacking vendor php build";
tar xzf ../$2;

echo "Building PHP Browscap Cache file";
php tools/ci/build-browscap-cache.php;
if [ $? -ne 0 ];
then
	echo "Build encountered an error";
	exit 1;
fi

if [ ! -f 'cache.php' ];
then
	echo "cache.php not found";
	exit 1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/browscap-artifacts;

echo "Copying artifact";
gzip cache.php;
cp -vf cache.php.gz $initialdir/browscap-artifacts/"$branch"-cache.php.gz;

echo "Artifact List: `readlink -f $initialdir/browscap-artifacts/`";
ls -lhR $initialdir/browscap-artifacts/;
