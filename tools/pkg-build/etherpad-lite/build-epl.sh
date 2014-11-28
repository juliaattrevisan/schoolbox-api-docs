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

eplver='1.4.1';

# Create directories for completing the build
builddir="/tmp/build-dir-`date -I`";
rm -fR $builddir;
mkdir $builddir;
cd $builddir;

# Retrieve Etherpad Lite source
wget -c https://github.com/ether/etherpad-lite/archive/$eplver.tar.gz;
tar xzf $eplver.tar.gz;

# Install dependencies and plugins
cd $builddir/etherpad-lite-$eplver/src;
npm install;
npm install redis;

# Install disable chat plugin
npm install ep_disableChat;

# Install disable author name change plugin
npm install ep_disable_change_author_name;

# Install headings plugins
npm install ep_headings;

# Add in upstart init script and logrotate
cd $builddir/etherpad-lite-$eplver;
cp -fR $fullpath/schoolbox-epl.conf .;
cp -fR $fullpath/schoolbox-epl.logrotate schoolbox-epl;
cd $builddir;

# Retar everything for the build process
tar czf schoolbox-epl_$eplver.orig.tar.gz etherpad-lite-$eplver;

# Copy deb build details to be ready for build
cd etherpad-lite-$eplver;
cp -fR $fullpath/debian .;

# Build!
debuild -i -I -us -uc;

echo "Build Directory: $builddir";
ls -lh $builddir/;
echo "";
echo "Build Completed";
