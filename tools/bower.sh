#!/bin/bash
# Bower update script
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au

# Go to parent directory of this file, and run Bower from there.
cd $(dirname $(dirname $(readlink -f $0)))

# I'm running as root user here anyway
bower --allow-root install

# Once Grunt is installed and part of build process, run Grunt tasks.
