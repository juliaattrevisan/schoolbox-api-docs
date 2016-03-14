#!/bin/bash

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "operating in dir: `pwd`";

# Install grunt
npm install grunt-cli;

# Build and install CSS and JS using grunt/bower
tools/pkg-build-css-js.sh;
