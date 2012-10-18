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

curdir=`pwd`;
cd ../updates;

# Only allow upgrades from 12.0.5 onwards
sqlupdatestart="-- End of Release 12.0.5";
echo $sqlupdatestart > "sql_updates.sql";
echo "" >> "sql_updates.sql";

# Auto create scripts_list and sql_updates.sql for each folder in updates
for release in `find . -maxdepth 1 -type d | sort | cut -c3-1000`;
do
	echo "Release: $release";
	echo "- Building scripts_list";
	cd $release;
	rm -f scripts_list;
	find . -type f | cut -c3-1000 | grep .sql >> scripts_list;
	find . -type f | cut -c3-1000 | grep .php >> scripts_list;
	echo "- Built scripts_list";

	cd ../;

	echo "- Starting creation of sql updates"
	for i in `grep ".sql" $release/scripts_list | grep -v "#"`;
	do
		cat $release/$i >> "sql_updates.sql";
		echo "" >> "sql_updates.sql";
	done

	echo "" >> "sql_updates.sql";
	echo "UPDATE \`config\` SET value='$release' WHERE name='site_version';" >> "sql_updates.sql";
	echo "" >> "sql_updates.sql";
	echo "-- End of Release $release" >> "sql_updates.sql";
	echo "" >> "sql_updates.sql";
	echo "- Created sql updates";
done

cd $curdir;

# End of script
