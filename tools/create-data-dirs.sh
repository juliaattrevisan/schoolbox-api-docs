#!/bin/bash
# Create Data Directories Script
# Description:
#	Performs a soft creation of each data directory
#	Only to be used by packaging post install
# Usage:
#	create-data-dirs.sh [base data directory] (optional)
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

echo "Updating data directories";

datadir='/var/lib/schoolbox';

if [ ! -z $1 ];
then
	if [ -d $1 ];
	then
		datadir=`readlink -f $1`;
	fi
fi

# First level dirs
mkdir -p $datadir/{dropbox,files,myFiles,returns,storage,submissions,thumbnails,video}

# Cache dirs
mkdir -p $datadir/cache/{minify,myfiles,portrait,purifier,stream,weather,xml,zip,logs}

# Cache subdirs for portrait and storage images
mkdir -p $datadir/cache/stream/{portrait,storage,socialstream}
mkdir -p $datadir/cache/stream/socialstream/thumbnails
mkdir -p $datadir/cache/submission_upload
mkdir -p $datadir/cache/plagscan_upload

# Create logs files so they have the correct perms SB-5193
touch $datadir/cache/logs/{sys,notif,db,sb,syn,admin,login,cms,cmd.sched,cmd.digest,audit}.log

# End of script
