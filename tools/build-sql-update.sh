#!/bin/bash
# Build Update Scripts
# Description:
#       Creates the updates/%release%/scripts_list file for script based updating
#       Updates the updates/sql_updates.sql file for package based updating
#
# Usage:
#       Needs to be run from the tools dir and commited before a release is built
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

tmpfile="/tmp/sql_updates_delta.sql";
curdir=`pwd`;

cd ../updates;
release=`find . -maxdepth 1 -type d | sort | tail -n 1 | cut -c3-1000`
echo "Release: $release";
echo "Building scripts_list";
cd $release;
rm -f scripts_list;
find . -type f | cut -c3-1000 | grep .sql >> scripts_list;
find . -type f | cut -c3-1000 | grep .php >> scripts_list;

echo "Built scripts_list";

cd ../;

rm -f $tmpfile;
echo "Starting creation of sql updates delta"
for i in `grep ".sql" $release/scripts_list | grep -v "#"`;do
        cat $release/$i >> $tmpfile
        echo "" >> $tmpfile
done
echo "" >> $tmpfile
echo "UPDATE \`config\` SET value='$release' WHERE name='site_version'" >> $tmpfile
echo "" >> $tmpfile
echo "-- End of Release $release" >> $tmpfile
echo "" >> $tmpfile
echo "Created sql updates delta";

echo "Creating new version files"
cat $tmpfile >> "sql_updates.sql";
sed -n '/\-\-\ End\ of\ Release/!p' $tmpfile >> "../sql/schoolbox-initial.sql"

rm -vf $tmpfile;

cd $curdir;

# End of script
