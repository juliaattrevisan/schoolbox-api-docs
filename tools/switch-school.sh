#!/bin/bash
# Switch school from command line
# Description:
#       Gives people a command line tool to switch school (site_location) easily.
#       Could be trivially extended to update any config variable.
#
# Usage:
#       tools/switch-school.sh [school]
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au


# Run from host instead of Vagrant guest?
# SSH into that, and pass this command thru to it.
ssh_config=`vagrant ssh-config 2>/dev/null`
if [ -n "$ssh_config" ]
then
	echo "Passing command through to Vagrant box"
	vagrant ssh-config | ssh default -q -F /dev/stdin "/vagrant/tools/switch-school.sh $1"
else
	# Yoinked out of update-sql.sh, thanks Sam
	filepath=`readlink -f $0`;
	fullpath=${filepath%/*};
	conffile=`readlink -f $fullpath/../www/conf/conf.php`;
	sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	echo "Updating site_location to \"$1\""
	mysql -u$sqluser -p$sqlpasswd -h $sqlhost -e "UPDATE config SET value='$1' WHERE name='site_location' LIMIT 1" $sqldb
fi

