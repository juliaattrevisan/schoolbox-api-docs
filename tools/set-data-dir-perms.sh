#!/bin/bash
# Set Data Directories Correct Permissions Script
# Description:
#	Sets the correct permissions for the data directories
#	Only to be used by packaging post install
# Usage:
#	set-data-dir-perms.sh [base data directory] [php-fpm user](optional)
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

# Check we're running as root
if [[ "$EUID" -ne 0 ]]
  then echo "Error - please run script as root."
  exit 1
fi

echo "Updating data directory permissions";

datadir='/var/lib/schoolbox';
wwwuser='www-data';

if [ ! -z $1 ];
then
	if [ -d $1 ];
	then
		datadir=`readlink -f $1`;
	fi
fi

if [ ! -z $2 ];
then
	wwwuser=$2;
fi

# Fix permissions
chown -R $wwwuser:$wwwuser $datadir
chmod -R 775 $datadir

echo "Data directory permissions updated";

# End of script
