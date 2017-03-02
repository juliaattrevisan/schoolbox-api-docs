#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "operating in dir: `pwd`";

echo "Creating artifact directory";
mkdir -vp $initialdir/package-artifacts;

echo "Building Package";
tools/pkg-build/ffmpeg/build.sh;
if [ $? -ne 0 ];
then
	echo "Build failed";
	exit 1;
fi

echo "Copy deb files to package-artifacts";
cp -vf ../*.deb $initialdir/package-artifacts;

cd $instdir;

echo "Artifact List: `readlink -f $initialdir/package-artifacts/`";
ls -lhR $initialdir/package-artifacts/;

echo "Other build items";
ls -lh $initialdir;

echo "Creating package tarball";
cd $initialdir;
tar czf ffmpeg-package.tar.gz package-artifacts;
if [ $? -ne 0 ];
then
	echo "Unable to tarball packages";
	exit 1;
fi

mv -v ffmpeg-package.tar.gz package-artifacts/;
