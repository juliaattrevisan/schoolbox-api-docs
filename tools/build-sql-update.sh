#!/bin/bash
# Build Update Scripts
# Description:
#       Creates the updates/%release%/scripts_list file for script based updating
#
# Usage:
#       Run automatically as part of the build/compile process
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

if [ ! -z $1 ];
then
	if [ -z $2 ];
	then
		basedir=`readlink -f debian/schoolbox/usr/share/schoolbox`;
	else
		basedir=`readlink -f debian/$2/usr/share/$2`;
	fi
else
	if [ -d '../updates' ];
	then
		basedir=`readlink -f ../`;
	else
		basedir=`readlink -f .`;
	fi
fi
baselength=`echo $basedir/updates/ | wc -m`;
echo "$basedir";

# Auto create scripts_list and sql_updates.sql for each folder in updates
for release in `find $basedir/updates/ -maxdepth 1 -type d | sort -V | cut -c$baselength-1000`;
do
	echo "Release: $release";
	echo "- Building scripts_list";
	basereleaselength=`echo $basedir/updates/$release/ | wc -m`;
	rm -f "$basedir/updates/$release/scripts_list";
	find $basedir/updates/$release -type f | cut -c$basereleaselength-1000 | grep .sql | sort >> "$basedir/updates/$release/scripts_list";
	find $basedir/updates/$release -type f | cut -c$basereleaselength-1000 | grep .php | sort >> "$basedir/updates/$release/scripts_list";
	echo "- Built scripts_list";
done

# End of script
