#!/bin/bash
# Create Data Directories Script
# Description:
#	Performs a soft creation of each data directory, and sets permissions
#	Only to be used by packaging post install
# Usage:
#	create-data-dirs.sh [base data directory] (optional)
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

echo "Updating data directory permissions";

datadir='/var/lib/schoolbox';

if [ ! -z $1 ];
then
	if [ -d $1 ];
	then
		datadir=`readlink -f $1`;
	fi
fi

# First level dirs
mkdir -p $datadir/{dropbox,files,myFiles,returns,storage,submissions,thumbnails,zip}

# Cache dirs
mkdir -p $datadir/cache/{minify,portrait,purifier,stream,weather,xml}

# Fix permissions
chown -R www-data:www-data $datadir
chmod -R 755 $datadir

# End of script