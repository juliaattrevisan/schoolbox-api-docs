#!/bin/bash
# DB Backup script
# Description:
#       Backs up the database contents to a file.
#       Places file in configured 'file_dir' directory.
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

echo "Starting DB backup process";

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;

# Collect Credentials
sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);

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
mysqldump -f --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u $sqluser -p$sqlpasswd $sqldb > $backupfile 2>&1;

echo "Backup of database at version $sqlver located at: $backupfile";
exit;
