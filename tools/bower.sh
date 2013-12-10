#!/bin/bash
# Bower update script
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au

# Go to parent directory of this file, and run Bower from there.
cd $(dirname $(dirname $(readlink -f $0)))

# Only attempt to install bower if it isn't installed globally already
`bower -v > /dev/null 2>&1`
if [ $? -ne 0 ];
then
    npm install -g bower;
fi;

# I'm running as root user here anyway
bower --allow-root install

# Once Grunt is installed and part of build process, run Grunt tasks.
