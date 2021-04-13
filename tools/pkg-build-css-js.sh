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

echo "Minifying and compiling JS and CSS";
if [ $production -eq 1 ];
then
  npm run publish
else
  npm run build
fi
if [ $? -ne 0 ];
then
	echo "Failed to build JS and CSS";
	exit -1;
fi
