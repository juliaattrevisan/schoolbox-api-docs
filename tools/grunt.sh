#!/bin/bash
#
# Grunt check script - checks if grunt-cli is installed
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        http://www.alaress.com.au

# Go to parent directory of this file, and run Bower from there.
cd $(dirname $(dirname $(readlink -f $0)))

# Once Grunt is installed and part of build process, run Grunt tasks.
`grunt -V > /dev/null 2>&1`
if [ $? -ne 0 ];
then
    npm install -g grunt-cli;
fi;
