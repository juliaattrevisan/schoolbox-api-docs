#!/bin/bash
# Module Install/Remove Script
# Description:
#       Enables or disable modules in the SQL config table as required
# Usage:
#       module.sh [enable/disable] [module_name] (both required)
#       module.sh check [module_name]
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#
export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games';
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

curdir=`pwd`;

helpmsg() {
	echo "Usage: module.sh [enable/disable] [module_name]";
	echo "Usage: module.sh check [module_name]";
	echo "Usage: module.sh set [config_name] [value]";
	exit 1;
}


if [ -z $1 ];
then
	helpmsg;
fi
if [ ! -z $1 ];
then
	if [ $1 == "-h" -o $1 == "--help" -o $1 == "-help" -o $1 == "help" ];
	then
		helpmsg;
	fi
fi

if [ ! -f $conffile ];
then
	echo "error: new installation or conf file missing";
	exit 1;
fi

# Collect Credentials
sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);


modulelist=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT name FROM config c JOIN lu_config_type lct ON c.type_id=lct.id WHERE (name LIKE '%enabled') OR name='debug_show_stack_trace' OR name='debug_mode'"`;
if [ $? -ne 0 ];
then
	echo "error: unable to list current modules";
	exit 1;
fi

if [ ! -z $1 ];
then
	if [  $1 == "--check" -o $1 == "-check" -o $1 == "check" -o $1 == "status" ];
	then
		if [ -z $2 ];
		then
			for module in `echo "$modulelist"`;
			do
				modcheck=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='$module'" 2>&1`;
				if [ -z $modcheck -o $modcheck == "0" ];
				then
					echo "$module - disabled";
				else
					if [ $modcheck == "1" ];
					then
						echo "$module - enabled";
					else
						echo "$module - unknown module status '$modcheck'";
					fi
				fi
			done
			exit 0;
		else
			module=$2;
			modcheck=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='$module'" 2>&1`;
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
					echo "unknown module status '$modcheck'";
					exit 1;
				fi
			fi
		fi		
	fi
	if [ $1 == "set" ];
	then
		if [  $2 == "kami_api_key" -o $2 == "site_type" -o $2 == "site_school_code" -o $2 == "debug_show_stack_trace" -o $2 == "debug_mode" -o $2 == "saml_ip_blacklist" -o $2 == "schoolbox_help_access_token" ];
		then
			mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='$3' WHERE name='$2'" 2>&1;
			if [ $? -ne 0 ];
			then
				echo "unable to set config '$2'";
				exit 0; # soft fail
			else
				echo "config '$2' set";
				exit 0;
			fi
		fi
	fi
	if [ ! -z $2 ];
	then
		module=$2;
		if [ "$module" == "mobile" ];
		then
			# hack to allow old mobile module to remove correctly :(
			exit 0;
		fi
		if [ `echo "$modulelist" | grep "$module" | wc -l` -ne 1 ];
		then
			echo "requested module '$module' does not exist";
			exit 1;
		fi
		if [ $1 == "enable" -o $1 == "enabled" ];
		then
			mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='1' WHERE name='$module'" 2>&1;
			if [ $? -ne 0 ];
			then
				echo "unable to enable '$module'";
				exit 1;
			else
				echo "module '$module' enabled";
				exit 0;
			fi
		fi
		if [ $1 == "disable" -o $1 == "disabled" ];
		then
			mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='0' WHERE name='$module'" 2>&1;
			if [ $? -ne 0 ];
			then
				echo "unable to enable '$module'";
				exit 1;
			else
				echo "module '$module' disabled";
				exit 0;
			fi
		fi
	fi
fi
