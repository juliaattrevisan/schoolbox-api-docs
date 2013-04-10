#!/bin/bash
# Generate Directory SQL Script
# Description:
#	Generates the SQL required to update the Schoolbox config table with
#	the locations of the template and data directories.
#	Used by the packaging scripts, the result is sent to stdout
# Usage:
#	generate-data-dir-sql.sh [base data dir] [base template dir] (optional)
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

datadir='/var/lib/schoolbox';
tpldir='/usr/share/schoolbox';

if [ ! -z $1 ];
then
	datadir=$1;
fi

if [ ! -z $2 ];
then
	tpldir=$2;
fi

echo "UPDATE config SET value='$tpldir/templates' WHERE name='template_dir';"
echo "UPDATE config SET value='$datadir/files' WHERE name='file_dir';"
echo "UPDATE config SET value='$datadir/myFiles' WHERE name='myfile_dir';"
echo "UPDATE config SET value='$datadir/submissions' WHERE name='submission_dir';"
echo "UPDATE config SET value='$datadir/returns' WHERE name='returns_dir';"
echo "UPDATE config SET value='$datadir/dropbox' WHERE name='dropbox_dir';"
echo "UPDATE config SET value='$datadir/cache/zip' WHERE name='zip_dir';"
echo "UPDATE config SET value='$datadir/thumbnails' WHERE name='thumbnail_dir';"
echo "UPDATE config SET value='$datadir/cache/xml' WHERE name='xml_dir';"
echo "UPDATE config SET value='$datadir/cache/stream' WHERE name='stream_dir';"
echo "UPDATE config SET value='$datadir/cache/portrait' WHERE name='portrait_dir';"
echo "UPDATE config SET value='$datadir/cache/weather' WHERE name='weather_dir';"
echo "UPDATE config SET value='$datadir/cache/minify' WHERE name='minify_dir';"
echo "UPDATE config SET value='$datadir/cache/myfiles' WHERE name='myfiles_cache_dir';"
echo "UPDATE config SET value='$datadir/cache/logs' WHERE name='logs_dir';"
echo "UPDATE config SET value='$datadir/storage' WHERE name='storage_dir';"
echo "UPDATE config SET value='$datadir/video' WHERE name='video_dir';"

# End of script
