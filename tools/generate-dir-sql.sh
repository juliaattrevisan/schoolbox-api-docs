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
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

datadir='/var/lib/schoolbox';

if [ ! -z $1 ];
then
	datadir=$1;
fi

#echo "UPDATE config SET value='$datadir/files' WHERE name='file_dir';"
echo "UPDATE config SET value='$datadir/cache/zip' WHERE name='zip_dir';"
echo "UPDATE config SET value='$datadir/purifier' WHERE name='purifier_cache_dir';"
echo "UPDATE config SET value='$datadir/twig' WHERE name='twig_cache_dir';"
echo "UPDATE config SET value='$datadir/cache/stream' WHERE name='stream_dir';"
echo "UPDATE config SET value='$datadir/cache/portrait' WHERE name='portrait_dir';"
echo "UPDATE config SET value='$datadir/cache/logs' WHERE name='logs_dir';"
echo "UPDATE config SET value='$datadir/storage' WHERE name='storage_dir';"

# End of script
