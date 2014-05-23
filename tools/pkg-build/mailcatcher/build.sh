#!/bin/bash
# Mailcatcher for Package Build Script
# Description:
#       Builds mailcatcher with it's specific gem dependencies
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

ver='0.5.12';

# Create directories for completing the build
builddir="/tmp/build-dir-`date -I`";
rm -fR $builddir;
mkdir $builddir;
cd $builddir;

# Install from rubygems
tardir="$builddir/mailcatcher-$ver";
mkdir -p $tardir;
gem install mailcatcher --version "=$ver" --install-dir "$tardir" --no-ri --no-rdoc

if [ $? -ne 0 ];
then
  echo "An error occurred";
  exit 1;
fi

# Add in upstart init script and logrotate
cd $tardir;
cp -fR $fullpath/mailcatcher.conf .;
cp -fR $fullpath/mailcatcher.logrotate mailcatcher;
cd $builddir;

# Retar everything for the build process
tar czf mailcatcher_$ver.orig.tar.gz mailcatcher-$ver;

# Copy deb build details to be ready for build
cd mailcatcher-$ver;
cp -fR $fullpath/debian .;

# Build!
debuild -i -I -us -uc;

echo "Build Directory: $builddir";
ls -lh $builddir/;
echo "";
echo "Build Completed";
