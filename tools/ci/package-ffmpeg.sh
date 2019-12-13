#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "operating in dir: `pwd`";

echo "Creating artifact directory";
mkdir -vp $initialdir/package-artifacts;

ver=`curl https://www.johnvansickle.com/ffmpeg/ 2>/dev/null | grep 'release:' | sed -e 's/<[^>]*>//g' | awk '{print $2}'`;
if [ "$ver" == "" ];
then
    echo "Error: unable to retrieve version";
fi

echo "Packaging ffmpeg version: $ver";

tardir="schoolbox-ffmpeg-$ver";
mkdir -p $tardir;

wget -c https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz;
if [ $? -ne 0 ];
then
  echo "An error occurred";
  exit 1;
fi

tar -xJvf ffmpeg-release-amd64-static.tar.xz;
mv -v ffmpeg-$ver-amd64-static $tardir/ffmpeg;

# Retar everything for the build process
tar czf schoolbox-ffmpeg_$ver.orig.tar.gz $tardir;

# Copy deb build details to be ready for build
cd $tardir;
cp -fR $instdir/tools/ci/ffmpeg/debian .;
sed -i "s/x.y.z/$ver/g" debian/changelog;

# Build!
debuild -i -I -us -uc;
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
