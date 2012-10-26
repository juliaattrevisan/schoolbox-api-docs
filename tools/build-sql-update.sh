#!/bin/bash
# Build Update Scripts
# Description:
#       Creates the updates/%release%/scripts_list file for script based updating
#       Updates the updates/sql_updates.sql file for package based updating
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
	basedir=`readlink -f ../`;
fi
baselength=`echo $basedir/updates/ | wc -m`;
echo "$basedir";

# Only allow upgrades from 12.0.5 onwards
sqlupdatestart="-- End of Release 12.0.5";
echo $sqlupdatestart > "$basedir/updates/sql_updates.sql";
echo "" >> "$basedir/updates/sql_updates.sql";

# Auto create scripts_list and sql_updates.sql for each folder in updates
for release in `find $basedir/updates -maxdepth 1 -type d | sort | cut -c$baselength-1000`;
do
	echo "Release: $release";
	echo "- Building scripts_list";
	basereleaselength=`echo $basedir/updates/$release/ | wc -m`;
	rm -f "$basedir/updates/$release/scripts_list";
	find $basedir/updates/$release -type f | cut -c$basereleaselength-1000 | grep .sql >> "$basedir/updates/$release/scripts_list";
	find $basedir/updates/$release -type f | cut -c$basereleaselength-1000 | grep .php >> "$basedir/updates/$release/scripts_list";
	echo "- Built scripts_list";

	echo "- Starting creation of sql updates"
	for i in `grep ".sql" $basedir/updates/$release/scripts_list | grep -v "#"`;
	do
		cat $basedir/updates/$release/$i >> "$basedir/updates/sql_updates.sql";
		echo "" >> "$basedir/updates/sql_updates.sql";
	done

	echo "" >> "$basedir/updates/sql_updates.sql";
	echo "UPDATE \`config\` SET value='$release' WHERE name='site_version';" >> "$basedir/updates/sql_updates.sql";
	echo "" >> "$basedir/updates/sql_updates.sql";
	echo "-- End of Release $release" >> "$basedir/updates/sql_updates.sql";
	echo "" >> "$basedir/updates/sql_updates.sql";
	echo "- Created sql updates";
done

# End of script
