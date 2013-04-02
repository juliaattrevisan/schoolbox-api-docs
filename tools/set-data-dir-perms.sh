#!/bin/bash
# Set Data Directories Correct Permissions Script
# Description:
#	Sets the correct permissions for the data directories
#	Only to be used by packaging post install
# Usage:
#	set-data-dir-perms.sh [base data directory] [apache user](optional)
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

echo "Updating data directory permissions";

datadir='/var/lib/schoolbox';
apacheuser='www-data';

if [ ! -z $1 ];
then
	if [ -d $1 ];
	then
		datadir=`readlink -f $1`;
	fi
fi

if [ ! -z $2 ];
then
	apacheuser=$2;
fi

# Fix permissions
chown -R $apacheuser:$apacheuser $datadir
chmod -R 775 $datadir

# End of script
