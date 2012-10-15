#!/bin/bash
# Clean cache directories script
# Description:
#       This script is to be run automatically upon version update to clean out specific cache directories
#       Note that only specifc cache directories are cleaned - not all caches
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

conffile1="../www/conf/conf.php";
conffile2="/etc/schoolbox/conf.php";
conffile3="/var/www/schoolbox/www/conf/conf.php";

echo "Starting Cache Cleaning process";

if [ -f $conffile1 ];
then
	conffile=$conffile1
else
	if [ -f $conffile2 ];
	then
		conffile=$conffile2
	else
		if [ -f $conffile3 ];
		then
			conffile=$conffile3
		else
			echo "New installation or conf file missing";
			echo "No cache directories cleaned";
			exit;
		fi
	fi
fi

echo "Using db credentials from: "$conffile;

if [ ! -f $sqlfile ];
then
	echo "SQL update file ($sqlfile) missing";
	echo "No cache directories cleaned";
	exit;
fi

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`

echo "Checking for SQL updates";

# Collect minify cache dir location
minifydir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='minify_dir' \G;" | grep 'value' | cut -d' ' -f2`

if [ -z $minifydir ];
then
	echo "Unable to find minify cache directory in config table. Please check your database credentials.";
	exit;
fi

if [ -d $minifydir ];
then
	rm -rf $minifydir/minify*;
	echo "Minify cache directory cleaned";
else
	echo "No cache directories cleaned";
fi

