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
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#
#
production=0

while [[ "$#" -gt 0 ]]; do
    case $1 in
        -p|--production) production=1 ;;
        *) echo "Unknown parameter passed: $1"; exit 1 ;;
    esac
    shift
done

if [ $production -eq 1 ];
then
  echo "Minifying and compiling JS and CSS for production";
  npm run publish
else
  echo "Minifying and compiling JS and CSS for development";
  npm run build
fi
if [ $? -ne 0 ];
then
	echo "Failed to build JS and CSS";
	exit -1;
fi
