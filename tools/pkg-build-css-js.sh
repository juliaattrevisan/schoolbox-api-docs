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

echo "NPM";
tools/pkg-build-npm.sh;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "Bower and Uglify/Min";
tools/pkg-build-bower.sh;
if [ $? -ne 0 ];
then
	echo "An error occurred";
	exit 1;
fi

echo "SASS";
if [ $sass -eq 1 ];
then
	tools/pkg-build-sass.sh --sass;
else
	tools/pkg-build-sass.sh;
fi
