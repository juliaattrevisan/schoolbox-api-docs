#!/bin/bash
# Module Install/Remove Script
# Description:
#       Enables module in SQL config and runs any SQL changes required
#       Disables the module in SQL config for removal, but leaves the SQL changes
#       so as not to remove history and allow reinstall
# Usage:
#       module.sh [install/enable/disable] [module_name] (both required)
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
	echo "";
	listmodules;
	exit 1;
}

listmodules() {
	echo "Schoolbox modules available:";
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

checkmodules() {
	echo "Schoolbox module status:";
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
}

if [ ! -z $1 ];
then
	if [  $1 == "--check" -o $1 == "-check" -o $1 == "check" -o $1 == "status" ];
	then
		checkmodules;
		exit 1;
	fi
fi

if [ ! -z $1 ];
then
	if [ ! -z $2 ];
	then
		if [ ! -d $instdir/sql/modules/$2 ];
		then
			echo "Requested module does not exist.";
			echo "";
			listmodules;
			exit 1;
		fi
		if [ $1 == "install" ];
		then
			echo "Install";
		fi
		if [ $1 == "enable" ];
		then
			echo "Enable";
		fi
		if [ $1 == "disable" ];
		then
			echo "Disable";
		fi
	else
		helpmsg;
	fi
else
	helpmsg;
fi