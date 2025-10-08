#!/bin/bash
# Clean cache directories script
# Description:
#       This script is to be run automatically upon version update to clean out specific cache directories
#       Note that only specifc cache directories are cleaned - not all caches
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#
#

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile="$fullpath/../www/conf/conf.php";
conffile=`readlink -f $conffile`;

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	echo "No cache directories cleaned";
	exit;
fi

# Collect Credentials
sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);

# Collect minify cache dir location
zipdir=`mariadb -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='zip_dir' \G;" | grep 'value' | cut -d' ' -f2`
purifierdir=`mariadb -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='purifier_cache_dir' \G;" | grep 'value' | cut -d' ' -f2`
streamdir=`mariadb -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='stream_dir' \G;" | grep 'value' | cut -d' ' -f2`
twigdir=`mariadb -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='twig_cache_dir' \G;" | grep 'value' | cut -d' ' -f2`

if [ -z $zipdir ];
then
    echo "Unable to find zip cache directory in config table. Please check your database credentials.";
    exit;
fi

if [ -z $purifierdir ];
then
    echo "Unable to find purifier cache directory in config table. Please check your database credentials.";
    exit;
fi

if [ -z $streamdir ];
then
    echo "Unable to find cache file directory in config table. Please check your database credentials.";
    exit;
fi

if [ -z $twigdir ];
then
    echo "Unable to find twig cache directory in config table. Please check your database credentials.";
    exit;
fi


if [ -d $zipdir ];
then
    rm -rf $zipdir/zip*;
    echo "Zip cache directory cleaned ($zipdir)";
else
    echo "No zip cache directories cleaned";
fi

if [ -d $purifierdir ];
then
    rm -rf "${purifierdir:?}"/*;
    echo "Purifier cache directory cleaned ($purifierdir)";
else
    echo "No purifier cache directories cleaned";
fi

if [ -d $streamdir ];
then
    for sub in $streamdir/*;
    do
        if [ -d $sub ];
        then
            rm -rf $sub/*
            echo "Stream cache directory cleaned ($sub)"
        else
            rm -f $sub
        fi
    done
    echo "Stream cache directory cleaned ($streamdir)"
else
    echo "No stream cache directories cleaned"
fi

if [ -d $twigdir ];
then
    rm -rf "${twigdir:?}"/*;
    echo "Twig templates cache cleaned ($twigdir)";
else
    echo "No twig templates cache directories cleaned";
fi
