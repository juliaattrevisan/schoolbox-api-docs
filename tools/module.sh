#!/bin/bash
# Module Install/Remove Script
# Description:
#       Enables module in SQL config and runs any SQL changes required
#       Disables the module in SQL config for removal, but leaves the SQL changes
#       so as not to remove history and allow reinstall
# Usage:
#       module.sh [install/enable/disable] [module_name] (both required)
#       module.sh check [module_name]
#       module.sh list
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

instdir=`readlink -f $fullpath/../`;
baselength=`echo $instdir/updates/ | wc -m`;
baselengthmodules=`echo $instdir/sql/modules/ | wc -m`;
package=${instdir##*/};

curdir=`pwd`;

helpmsg() {
	echo "Usage: module.sh [install/enable/disable] [module_name]";
	echo "Usage: module.sh check [module_name]";
	echo "Usage: module.sh list";
	echo "";
	echo "Schoolbox modules available:";
	listmodules;
	exit 1;
}

listmodules() {
	for module in `find $instdir/sql/modules/ -maxdepth 1 -type d | cut -c$baselengthmodules-1000`;
	do
		echo "* $module";
	done
}

if [ ! -z $1 ];
then
	if [ $1 == "-h" -o $1 == "--help" -o $1 == "-help" -o $1 == "help" ];
	then
		helpmsg;
	fi
	if [ $1 == "-l" -o $1 == "--list" -o $1 == "-list" -o $1 == "list" ];
	then
		listmodules;
		exit 1;
	fi
fi

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	exit;
fi

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;

if [ ! -z $1 ];
then
	if [  $1 == "--check" -o $1 == "-check" -o $1 == "check" -o $1 == "status" ];
	then
		if [ -f $instdir/sql/modules/$2/check.sql ];
		then
			modcheck=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $instdir/sql/modules/$2/check.sql | grep 'value' | cut -d' ' -f2`;
			if [ -z $modcheck -o $modcheck == "0" ];
			then
				echo "disabled";
				exit 0;
			else
				if [ $modcheck == "1" ];
				then
					echo "enabled";
					exit 0;
				else
					echo "unknown";
					exit 1;
				fi
			fi
		fi
		for module in `find $instdir/sql/modules/ -maxdepth 1 -type d | cut -c$baselengthmodules-1000`;
		do
			if [ -f $instdir/sql/modules/$module/check.sql ];
			then
				modcheck=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $instdir/sql/modules/$module/check.sql | grep 'value' | cut -d' ' -f2`;
				if [ -z $modcheck -o $modcheck == "0" ];
				then
					echo "* $module [disabled]";
				else
					if [ $modcheck == "1" ];
					then
						echo "* $module [enabled]";
					else
						echo "* $module [unknown]";
					fi
				fi
			else
				echo "* $module [unknown]";
			fi
		done
		exit 1;
	fi
fi

backupdir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='file_dir' \G;" | grep 'value' | cut -d' ' -f2`;
backupdir=`readlink -f $backupdir/../`
backupfile="$backupdir/`date +%Y-%m-%d_%H-%M-%S`.sql";

if [ ! -z $1 ];
then
	if [ ! -z $2 ];
	then
		if [ ! -d $instdir/sql/modules/$2 ];
		then
			echo "Requested module $2 does not exist.";
			echo "";
			echo "Schoolbox modules available:";
			listmodules;
			exit 1;
		fi
		if [ $1 == "install" ];
		then
			modcheck=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $instdir/sql/modules/$2/check.sql | grep 'value' | cut -d' ' -f2`;
			if [ -z $modcheck -o $modcheck == "0" ];
			then
				echo "Backing up database before changes";
				mkdir -p $backupdir;
				mysqldump --allow-keywords -i -e -q -a -c -u $sqluser -p$sqlpasswd $sqldb > $backupfile 2>&1;
				echo "Backup of database before installing $2 located at: $backupfile";
				mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $instdir/sql/modules/$2/install.sql 2>&1;
				if [ $? -ne 0 ];
				then
					echo "Unable to install $2.";
					exit 1;
				else
					echo "Module $2 installed";
					exit 0;
				fi
			else
				echo "Module $2 already enabled, no changes made";
			fi
		fi
		if [ $1 == "enable" ];
		then
			mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $instdir/sql/modules/$2/enable.sql 2>&1;
			if [ $? -ne 0 ];
			then
				echo "Unable to enable $2.";
				exit 1;
			else
				echo "Module $2 enabled";
				exit 0;
			fi
		fi
		if [ $1 == "disable" ];
		then
			mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $instdir/sql/modules/$2/disable.sql 2>&1;
			if [ $? -ne 0 ];
			then
				echo "Unable to disable $2.";
				exit 1;
			else
				echo "Module $2 disabled";
				exit 0;
			fi
		fi
	else
		helpmsg;
	fi
else
	helpmsg;
fi