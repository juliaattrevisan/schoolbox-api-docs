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

echo "Creating artifact directory";
mkdir -vp $initialdir/built-artifacts;

echo "Creating artifact checksum";
sha1sum composer.lock > $initialdir/built-artifacts/"$branch"-vendor-php-checksum-1.0.0.txt;

echo "Creating artifact";
tar czvf $initialdir/built-artifacts/"$branch"-vendor-php-1.0.0.tar.gz vendor;

echo "Artifact List: `readlink -f $initialdir/built-artifacts/`";
ls -lhR $initialdir/built-artifacts/;
