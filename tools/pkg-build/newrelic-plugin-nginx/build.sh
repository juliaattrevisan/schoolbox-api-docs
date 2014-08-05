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

ver='1.2.1';

# Create directories for completing the build
builddir="/tmp/build-dir-`date -I`";
rm -fR $builddir;
mkdir $builddir;
cd $builddir;

# Install from rubygems
tardir="$builddir/newrelic-plugin-nginx-$ver";
mkdir -p $tardir;
#gem install mailcatcher --version "=$ver" --install-dir "$tardir" --no-ri --no-rdoc
wget http://nginx.com/download/newrelic/newrelic_nginx_agent.tar.gz;
tar xzvf newrelic_nginx_agent.tar.gz;
mv newrelic_nginx_agent/* $tardir/ -vf;
rm -vfR newrelic_nginx_agent;

if [ $? -ne 0 ];
then
  echo "An error occurred";
  exit 1;
fi

# Install dependencies
cd $tardir;
#gem install newrelic_plugin --install-dir "$tardir" --no-ri --no-rdoc
#gem install daemons --install-dir "$tardir" --no-ri --no-rdoc
bundle install --path .;

# Add in upstart init script and logrotate
cd $tardir;
cp -fR $fullpath/newrelic-plugin-nginx.conf .;
cd $builddir;

# Retar everything for the build process
tar czf newrelic-plugin-nginx_$ver.orig.tar.gz newrelic-plugin-nginx-$ver;

# Copy deb build details to be ready for build
cd newrelic-plugin-nginx-$ver;
cp -fR $fullpath/debian .;

# Build!
debuild -i -I -us -uc;

echo "Build Directory: $builddir";
ls -lh $builddir/;
echo "";
echo "Build Completed";
