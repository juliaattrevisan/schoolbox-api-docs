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

if [ "$branch" == "master" ];
then
	prefix='';
else
	prefix="$branch-";
fi

echo "Branch: $branch";

echo "Checking version to build";
if [ -f ../schoolbox-release-$branch/version ];
then
	version=`cat ../schoolbox-release-$branch/version`;
else
	echo "Unable to determine version";
	exit 1;
fi

if [ ! -f ../"$prefix"build-artifact/"$branch"-build.tar.gz ];
then
	echo "Unable to locate build artifact: ../"$prefix"build-artifact/"$branch"-build.tar.gz";
	exit 1;
fi

for i in schoolbox{,dev,cloud};
do
	echo "Creating package directory: $i";
	mkdir -vp $initialdir/$i-$version;

	echo "Unpacking build artifact for: $i";
	cd $initialdir/$i-$version;
	tar xzf ../"$prefix"build-artifact/"$branch"-build.tar.gz;
done

echo "Creating artifact directory";
mkdir -vp $initialdir/package-artifacts;

#echo "Creating build tarball artifact";
#tar czf $initialdir/package-artifacts/"$prefix"build.tar.gz .;

echo "Artifact List: `readlink -f $initialdir/package-artifacts/`";
ls -lhR $initialdir/package-artifacts/;
