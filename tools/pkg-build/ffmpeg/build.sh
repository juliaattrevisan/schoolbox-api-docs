#!/bin/bash
# FFmpeg for Package Build Script
# Description:
#       Package static build of FFmpeg for Schoolbox
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2015 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

ver='2.6.3';

# Create directories for completing the build
builddir="/tmp/build-dir-`date -I`";
rm -fR $builddir;
mkdir $builddir;
cd $builddir;

# Install from rubygems
tardir="$builddir/schoolbox-ffmpeg-$ver";
mkdir -p $tardir;

wget -c http://johnvansickle.com/ffmpeg/releases/ffmpeg-release-64bit-static.tar.xz;
if [ $? -ne 0 ];
then
  echo "An error occurred";
  exit 1;
fi

tar -xJvf ffmpeg-release-64bit-static.tar.xz;
mv -v ffmpeg-$ver-64bit-static $tardir/ffmpeg;

# Retar everything for the build process
tar czf schoolbox-ffmpeg_$ver.orig.tar.gz schoolbox-ffmpeg-$ver;

# Copy deb build details to be ready for build
cd schoolbox-ffmpeg-$ver;
cp -fR $fullpath/debian .;

# Build!
debuild -i -I -us -uc;

echo "Build Directory: $builddir";
ls -lh $builddir/;
echo "";
echo "Build Completed";
