#!/bin/bash
# Bower update script
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au

# Packages are now handled by NPM
npm install
grunt build-js
