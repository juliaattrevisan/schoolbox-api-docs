#!/bin/bash
# Bower update script
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

# Packages are now handled by NPM
npm install
grunt build.js
