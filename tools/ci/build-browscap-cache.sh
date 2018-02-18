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

if [ "$branch" != 'release' ];
then
	echo "Unpacking vendor php build";
	tar xzf ../$2;
	if [ $? -ne 0 ];
	then
		echo "Unable to unpack vendor php tarball";
		exit 1;
	fi
fi

echo "Building PHP Browscap Cache file";
vendor/browscap/browscap-php/bin/browscap-php browscap:update --cache=$instdir/tools/browscap/ --remote-file=Lite_PHP_BrowscapINI
if [ $? -ne 0 ];
then
	echo "Build encountered an error";
	exit 1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/browscap-artifacts;

echo "Archiving Cache Directory"
tar czvf $initialdir/browscap-artifacts/"$branch"-cache.tar.gz tools/browscap;

echo "Artifact List: `readlink -f $initialdir/browscap-artifacts/`";
ls -lhR $initialdir/browscap-artifacts/;
