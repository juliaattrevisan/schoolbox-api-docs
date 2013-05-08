#!/bin/bash
# Removes cron job files from packages
# Description
#       Before packaging, the cronjob files not for the requested package need to be removed
#
# Usage:
#       pkg-remove-other-crons.sh [schoolbox|schoolboxdev]
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

helpmsg() {
	echo "Usage: pkg-remove-other-crons.sh [schoolbox|schoolboxdev]";
	exit 1;
}

if [ -z $1 ];
then
	helpmsg;
fi

if [ "$1" == 'schoolbox' ];
then
	cronjob='schoolboxdev';
else
	if [ "$1" == 'schoolboxdev' ];
	then
		cronjob='schoolbox';
	else
		helpmsg;
	fi
fi

rm -vf `readlink -f $fullpath/cron/$cronjob`;

# End of script
