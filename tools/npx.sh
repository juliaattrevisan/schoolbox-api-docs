#!/bin/bash
#
# NPX check script - checks if npx is installed (and install if not)

# Go to parent directory of this file
cd $(dirname $(dirname $(readlink -f $0)))

# Check if NPX is installed via -v, and install if not
`npx -v > /dev/null 2>&1`
if [ $? -ne 0 ];
then
    npm install -g npx;
fi;
