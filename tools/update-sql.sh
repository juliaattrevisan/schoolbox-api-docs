#!/bin/bash
# SQL Database Update Script
# Description:
#       Updates the database schema and values to match the installed version
#       Completes all the sql updates between the current and new versions
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

# newver (new version) to be updated during packaging
newver="x.x.x";

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile="$fullpath/../www/conf/conf.php";
conffile=`readlink -f $conffile`;

instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

sqlfile=$instdir"/updates/sql_updates.sql";
tmpfile="/tmp/$package_sql_updates_`date -I`.sql";
backupdir="/var/lib/$package";
backupfile="$backupdir/`date +%Y-%m-%d_%H-%M-%S`.sql";

echo "Starting SQL update process";

if [ $newver == "x.x.x" ];
then
	echo "Please specify the new sql version in "`readlink -f $0`;
	exit;
fi

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	echo "No cache directories cleaned";
	exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;

if [ ! -f $sqlfile ];
then
	echo "SQL update file ($sqlfile) missing";
	echo "No SQL updates performed";
	exit;
fi

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`

echo "Checking for SQL updates";

# Collect current SQL version
sqlver=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2`

if [ -z $sqlver ];
then
	echo "Unable to collect current database version. Please check your database credentials.";
	exit;
fi

echo "Current SQL Version: "$sqlver;
echo "New SQL Version: "$newver;

if [ $sqlver = $newver ];
then
	echo "No SQL updates required";
else 
	start=`grep -n "\-\-\ End\ of\ Release\ $sqlver" $sqlfile | cut -d':' -f1`;
	end=`grep -n "\-\-\ End\ of\ Release\ $newver" $sqlfile | cut -d':' -f1`;
	
	if [ -z $start ];
	then
		echo "SQL version greater than update, no changes made";
		exit;
	fi
	
	if [ $start -gt $end ];
	then
		echo "SQL version greater than update, no changes made";
		exit;
	fi
	
	echo "Backing up database before changes";
	mkdir -p $backupdir;
	mysqldump --allow-keywords -i -e -q -a -c -u $sqluser -p$sqlpasswd $sqldb > $backupfile;
	
	sed -n "$start","$end"p $sqlfile | sed -n '/\-\-\ End\ of\ Release/!p' > $tmpfile;
	mysql -h $sqlhost -u $sqluser -p$sqlpasswd $sqldb < $tmpfile;
	
	if [ $? -eq 0 ];
	then
		echo "SQL updated to version $newver";
	else
		# Drop all tables in database before restore (drop database may not be available)
		for table in `mysql -u $sqluser  -p$sqlpasswd $sqldb -e 'show tables'| grep -v Tables_in`;
		do
			mysql -u $sqluser -p$sqlpasswd $sqldb -e "drop table $table";
		done;
		# Revert database to original
		mysql -h $sqlhost -u $sqluser -p$sqlpasswd $sqldb < $backupfile > /dev/null 2>&1;
		if [ $? -eq 0 ];
		then
			echo "Database returned to original pre-update state.";
		fi
		echo "SQL update failed. Please attempt a manual update.";
		echo "A backup of the original database is located at: $backupfile";
	fi
	
	# Cleanup tmpfile and backup
	if [ -f $tmpfile ];
	then
		rm -f $tmpfile;
	fi
fi

