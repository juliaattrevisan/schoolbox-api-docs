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

if [ -f .git/ref ];
then
    buildcommit=`cat .git/ref`;
    echo "{\"build_commit\":\"$buildcommit\"}" > version.json;
else
    echo "Unable to determine build commit";
    ls -1 .git/;
fi
echo "Build commit: $buildcommit";
cat version.json;


if [ ! -f ../vendor-php-artifact/"$branch"-vendor-php.tar.gz ];
then
	echo "Unable to locate vendor artifact: ../vendor-php-artifact/"$branch"-vendor-php.tar.gz";
	exit 1;
fi

echo "Unpacking vendor php build";
tar xzf ../vendor-php-artifact/"$branch"-vendor-php.tar.gz;

echo "Validating checksums";
checksum=`sha1sum composer.lock`;
if [ "$checksum" != "`cat vendor/composer.lock.checksum`" ];
then
        echo "Vendor checksum does not match";
        exit 1;
fi

if [ ! -f ../bower-artifact/"$branch"-bower.tar.gz ];
then
	echo "Unable to locate bower artifact: ../bower-artifact/"$branch"-bower.tar.gz";
	exit 1;
fi

echo "Unpacking bower build";
tar xzf ../bower-artifact/"$branch"-bower.tar.gz;

echo "Validating checksums";
checksum=`sha1sum bower.json`;
if [ "$checksum" != "`cat www/javascript/components/bower.checksum`" ];
then
        echo "Bower checksum does not match";
        exit 1;
fi

echo "Removing undeployable files";
if [ -f tools/ci/undeployable.list ];
then
	for i in `cat tools/ci/undeployable.list`;
	do
		if [ "$i" != "" ];
		then
			rm -vfR "$i";
		fi
	done
fi

echo "Building SQL Update list";
tools/build-sql-update.sh;

echo "Creating artifact directory";
mkdir -vp $initialdir/tarball-artifacts;

echo "Creating build tarball artifact";
tar czf $initialdir/tarball-artifacts/build.tar.gz .;

echo "Artifact List: `readlink -f $initialdir/tarball-artifacts/`";
ls -lhR $initialdir/tarball-artifacts/;
