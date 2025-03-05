#!/bin/bash
# Create Data Directories Script
# Description:
#	Performs a soft creation of each data directory
#	Only to be used by packaging post install
# Usage:
#   create-data-dirs.sh [base data directory] (optional)
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

echo "Updating data directories";

data_dir='/var/lib/schoolbox';

if [ -n "$1" ] && [ -d "$1" ]; then
  data_dir=$(readlink -f "$1");
fi

# First level dirs
mkdir -p "$data_dir"/{cache,files,purifier,storage,twig}

# Cache dirs
mkdir -p "$data_dir"/cache/{portrait,stream,zip}

# End of script
