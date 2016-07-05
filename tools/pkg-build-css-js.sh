#!/bin/bash
# Builds all CSS and Javascript for provisioning and packaging
# Description
#       Installs dependencies for CSS and JS build process
#       Builds all JS and CSS
#
# Usage:
#       pkg-build-css-js.sh
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

sass=0
if [ "$1" == "--sass" ]; then
	sass=1
fi

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../`;
PATH="$PATH:$instdir/node_modules/.bin";

echo "Installing NPM dependencies";
npm install;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "Install SASS";
gem install sass --no-rdoc --no-ri --version "3.4.13"
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

# Last test: bundler works but is MUCH slower to install than through gem
#apt-get -y install ruby-bundler;
#bundle install;

echo "Installing Bower submodules";
grunt bower;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "Building JS files via UglifyJS";
grunt build.js;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

if [ $sass = 1 ]; then
	echo "Build SASS files (Vagrant provision only, not package build)";
	grunt sass;
fi
