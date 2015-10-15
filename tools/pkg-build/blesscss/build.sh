#!/bin/bash
# Etherpad Lite for Schoolbox Build Script
# Description:
#       Builds Etherpad Lite as schoolbox-epl along with required plugins
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

ver='3.0.3';

# Create directories for completing the build
builddir="/tmp/build-dir-`date -I`";
rm -fR $builddir;
mkdir $builddir;
cd $builddir;

mkdir blesscss-$ver;
cd blesscss-$ver;

# Install dependencies and plugins
npm install bless@$ver;

# Retar everything for the build process
cd ..;
tar czf blesscss_$ver.orig.tar.gz blesscss-$ver;

# Copy deb build details to be ready for build
cd -$eplver;
cp -fR $fullpath/debian .;

# Build!
debuild -i -I -us -uc;

echo "Build Directory: $builddir";
ls -lh $builddir/;
echo "";
echo "Build Completed";
