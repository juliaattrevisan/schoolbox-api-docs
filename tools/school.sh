#!/bin/bash
# Switch school from command line
# Description:
#       Gives people a command line tool to get and set school (site_location)
#       easily.
#       Could be trivially extended to update any config variable.
#
# Usage:
#       tools/school.sh [school]
#       If school is not provided, outputs the current school - otherwise, it
#       sets it to whatever value is provided.
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/


# Run from host instead of Vagrant guest?
# SSH into that, and pass this command thru to it.
ssh_config=`vagrant ssh-config 2>/dev/null`
if [ -n "$ssh_config" ]
then
	if [ "$1" != "" ]
	then
		# Output nothing if passing thru, we just want to get the school name.
		echo "Passing command through to Vagrant box"
	fi
	vagrant ssh-config | ssh default -q -F /dev/stdin "/vagrant/tools/school.sh $*"
else
	# Yoinked out of update-sql.sh, thanks Sam
	filepath=`readlink -f $0`;
	fullpath=${filepath%/*};
	conffile=`readlink -f $fullpath/../www/conf/conf.php`;
	sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
	sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
	sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
	sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);
	if [ "$1" == "" ]
	then
		mysql --skip-column-names -u$sqluser -p$sqlpasswd -h $sqlhost -e "SELECT value FROM config WHERE name='site_location' LIMIT 1" $sqldb
	else
		echo "Updating site_location to \"$1\""
		mysql -u$sqluser -p$sqlpasswd -h $sqlhost -e "UPDATE config SET value='$1' WHERE name='site_location' LIMIT 1" $sqldb
		mysql -u$sqluser -p$sqlpasswd -h $sqlhost -e "UPDATE config SET value='$1' WHERE name='site_school_code' LIMIT 1" $sqldb

	fi
fi

