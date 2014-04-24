#!/bin/bash
# Cleanup and conversion for live or dev packaging
# Description
#       Before packaging, the cronjob files not for the requested package need to be removed
#	Also performs and renames or replacements as required
#
# Usage:
#       pkg-live-vs-dev.sh [schoolbox|schoolboxdev]
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

helpmsg() {
	echo "Usage: pkg-live-vs-dev.sh [schoolbox|schoolboxdev]";
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
		sed -i 's/schoolbox/schoolboxdev/g' $fullpath/tools/init/*.conf;
		sed -i 's/Schoolbox/Schoolbox\ Dev/g' $fullpath/tools/init/*.conf;
	else
		helpmsg;
	fi
fi

rm -vf `readlink -f $fullpath/cron/$cronjob`;
rm -vf `readlink -f $fullpath/logrotate/$cronjob`;

# End of script
