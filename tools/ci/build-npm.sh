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

tools/pkg-build-npm.sh;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

tools/pkg-build-css-js.sh
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/built-artifacts;

echo "Creating artifact";
tar czf $initialdir/built-artifacts/"$branch"-npm.tar.gz node_modules;

echo "Artifact List: `readlink -f $initialdir/built-artifacts/`";
ls -lhR $initialdir/built-artifacts/;
