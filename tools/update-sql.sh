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

backupdir="/var/lib/$package";
backupfile="$backupdir/`date +%Y-%m-%d_%H-%M-%S`.sql";

curdir=`pwd`;

echo "Starting SQL update process";

# Build update version list
rm -f $versionlist;
for release in `echo "12.0.5";find $instdir/updates -maxdepth 1 -type d | sort -V | cut -c$baselength-1000`;
do
	echo $release >> $versionlist;
	newver=$release;
done

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	echo "No cache directories cleaned";
	exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;

echo "Checking for SQL updates";

# Collect current SQL version
sqlver=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2`;

if [ -z $sqlver ];
then
	echo "Unable to collect current database version. Please check your database credentials.";
	exit;
fi

echo "Current SQL Version: "$sqlver;
echo "New SQL Version: "$newver;

if [ $sqlver == $newver ];
then
	echo "No updates required";
	exit;
fi

start=`grep -n "$sqlver" $versionlist | cut -d':' -f1`;
end=`grep -n "$newver" $versionlist | cut -d':' -f1`;

if [ -z $start ];
then
	echo "SQL version not supported for update, no changes made";
	exit;
fi

if [ -z $end ];
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
mysqldump --allow-keywords -i -e -q -a -c -u $sqluser -p$sqlpasswd $sqldb > $backupfile 2>&1;

echo "Updating database to new version";
errorint=0;
# Run update scripts in release order, SQL first then PHP for each release
for release in `sed -n "$start","$end"p $versionlist`;
do
	# Don't run update if already at that release version
	if [ $release != $sqlver ];
	then
		echo "Running update $release";
		# Only run release upgrade if the scripts_list actually exists
		if [ -f $instdir/updates/$release/scripts_list ];
		then
			# Run SQL update scripts for this release
			for sqlscript in `grep "\.sql" $instdir/updates/$release/scripts_list`
			do
				script="$instdir/updates/$release/$sqlscript";
				# Check script listed in scripts_list actually exists
				if [ -f $script ];
				then
					mysql -h $sqlhost -u $sqluser -p$sqlpasswd $sqldb < $script 2>&1;
					# Check for and report errors
					if [ $? -ne 0 ];
					then
						echo "Error running update: $script";
						errorint=1;
					fi
				fi
			done
			
			# Run PHP update scripts for this release
			for phpscript in `grep "\.php" $instdir/updates/$release/scripts_list`
			do
				script="$instdir/updates/$release/$phpscript";
				# Check script listed in scripts_list actually exists
				if [ -f $script ];
				then
					scriptpath=${script%/*};
					cd $scriptpath;
					phpscript=${phpscript##*/};
					php -f $phpscript 2>&1;
					# Check for and report errors
					if [ $? -ne 0 ];
					then
						cd $curdir;
						echo "Error running update: $script";
						errorint=1;
					fi
					cd $curdir;
				fi
			done
			
			# Update the config table to show current release version
			mysql -h $sqlhost -u $sqluser -p$sqlpasswd $sqldb -e "UPDATE \`config\` SET value='$release' WHERE name='site_version';";
		fi
	fi
done

echo "Backup of database at version $sqlver located at: $backupfile";

if [ $errorint -ne 0 ];
then
	echo "SQL updates completed with errors. Please manually fix issues or rollback to pre-update backup.";
else
	echo "SQL updates completed";
fi
