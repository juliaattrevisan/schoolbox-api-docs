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

PATH="$PATH:$instdir/node_modules/.bin";

echo "Building Bower submodules";
grunt bower;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "Building JS files via UglifyJS";
tools/pkg-build-bower.sh;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/built-artifacts;

echo "Creating artifact checksum";
sha1sum bower.json > www/javascript/components/bower.checksum;

echo "Creating artifact";
tar czf $initialdir/built-artifacts/"$branch"-bower.tar.gz www/javascript/components;

echo "Artifact List: `readlink -f $initialdir/built-artifacts/`";
ls -lhR $initialdir/built-artifacts/;
