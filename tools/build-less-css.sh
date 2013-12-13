#!/bin/bash
# Build CSS file from less versions
# Description:
#       Creates css files from less files for core template and mobile templates
#
# Usage:
#       Run automatically as part of the build/compile process
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au

# Packages are now handled by NPM
npm install
grunt build.css
