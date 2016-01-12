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

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile="$fullpath/../www/conf/conf.php";
conffile=`readlink -f $conffile`;

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	echo "No cache directories cleaned";
	exit;
fi

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`

# Collect minify cache dir location
minifydir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='minify_dir' \G;" | grep 'value' | cut -d' ' -f2`
zipdir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='zip_dir' \G;" | grep 'value' | cut -d' ' -f2`
xmldir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='xml_dir' \G;" | grep 'value' | cut -d' ' -f2`
streamdir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='stream_dir' \G;" | grep 'value' | cut -d' ' -f2`

if [ -z $minifydir ];
then
	echo "Unable to find minify cache directory in config table. Please check your database credentials.";
	exit;
fi

if [ -z $zipdir ];
then
	echo "Unable to find zip cache directory in config table. Please check your database credentials.";
	exit;
fi

if [ -z $xmldir ];
then
	echo "Unable to find xml cache directory in config table. Please check your database credentials.";
	exit;
else
	purifierdir="$xmldir/../purifier/";
	if [ -z $purifierdir ];
	then
		echo "Unable to find purifier cache directory in config table. Please check your database credentials.";
		exit;
	fi
fi

if [ -z $streamdir ];
then
	echo "Unable to find cache file directory in config table. Please check your database credentials.";
	exit;
fi


if [ -d $minifydir ];
then
	rm -f $minifydir/minify*;
	echo "Minify cache directory cleaned ($minifydir)";
else
	echo "No minify cache directories cleaned";
fi

if [ -d $zipdir ];
then
	rm -rf $zipdir/zip*;
	echo "Zip cache directory cleaned ($zipdir)";
else
	echo "No zip cache directories cleaned";
fi

if [ -d $purifierdir ];
then
	rm -rf $purifierdir/*;
	echo "Purifier cache directory cleaned ($purifierdir)";
else
	echo "No purifier cache directories cleaned";
fi

if [ -d $streamdir ];
then
	rm -f $streamdir/*;
	echo "File cache directory cleaned ($streamdir)";
	rm -fR $streamdir/templates/*;
	echo "Twig templates cache cleaned ($streamdir/templates)";
else
	echo "No stream cache directories cleaned";
fi



