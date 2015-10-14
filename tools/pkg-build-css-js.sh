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

# Installing NPM dependencies
npm install;

# Install SASS
gem install sass --no-rdoc --no-ri --version "3.4.13"
# Last test: bundler works but is MUCH slower to install than through gem
#apt-get -y install ruby-bundler;
#bundle install;

# Installing and building Bower submodules
grunt bower build.deps;

# Building JS files via UglifyJS
grunt build.js;

# Building CSS files via LessCSS
grunt build.css;

# Build SASS files (Vagrant provision only, not package build)
if [ $sass = 1 ]; then
    grunt sass;
fi
