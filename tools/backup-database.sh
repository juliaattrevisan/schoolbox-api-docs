#!/bin/bash
# SQL Update Script
# Description:
#       Updates the database schema and values to match the installed version
#       Completes all the updates between the current and new versions
#       For each update release, the sql scripts are run first, then the php scripts
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

versionlist="/tmp/sb_version_list";

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

instdir=`readlink -f $fullpath/../`;
baselength=`echo $instdir/updates/ | wc -m`;
package=${instdir##*/};

curdir=`pwd`;

echo "Starting DB backup process";

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;

# Collect current SQL version
sqlver=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2`;

if [ -z $sqlver ];
then
	echo "Unable to collect current database version. Please check your database credentials.";
	exit;
fi

backupdir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='file_dir' \G;" | grep 'value' | cut -d' ' -f2`;
backupdir=`readlink -f $backupdir/../`
backupfile="$backupdir/$sqlver-`date +%Y-%m-%d_%H-%M-%S`.sql";

mkdir -p $backupdir;
mysqldump --allow-keywords -i -e -q -a -c -u $sqluser -p$sqlpasswd $sqldb > $backupfile 2>&1;

echo "Backup of database at version $sqlver located at: $backupfile";
exit;
