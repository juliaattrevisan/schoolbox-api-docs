#!/bin/bash
# Instance ID generator
# Description
#       Generates a new instance id for a fresh install
#       Optionally with -f will change the instance id of a current install
#
# Usage:
#       update-instance-id.sh [-f]
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

filepath=`readlink -f $0`;
fullpath=${filepath%/*};
instdir=`readlink -f $fullpath/../`;

conffile=`readlink -f $fullpath/../www/conf/conf.php`;
confdir=${conffile%/*};

error() {
	# Display an error message in red then exit with error code
	echo -e "\033[38;5;1m"
	echo -e "Error: $errmsg\n";
	echo -e "\033[39m";
	exit 1;
}

if [ ! -f $conffile ];
then
	errmsg="New installation or conf file missing";
	error;
fi

newinstanceid=`openssl rand -base64 32 | sha1sum | cut -c1-8`;

if [ `grep "define('INSTANCE_ID'," $conffile | wc -l` -ne 0 ];
then
	if [ "$1" == "-f" ];
	then
		echo "Instance ID already defined - forcing change";
		cp -f $conffile $confdir/pre14.0-`date -I`-conf.php;
		tempfile='/tmp/old.schoolbox.conf.php';
		tline=`grep -n "define('INSTANCE_ID'," $conffile | cut -d':' -f1`;
		pline=`grep -n "define('INSTANCE_ID'," $conffile | cut -d':' -f1 | wc -l`;
		wcfile=`wc -l $conffile | awk {'print $1'}`;
		head -n `expr $tline - 1` $conffile > $tempfile;
		echo "define('INSTANCE_ID', '$newinstanceid');" >> $tempfile;
		tail -n `expr $wcfile - $tline` $conffile >> $tempfile;
		cp -f $tempfile $conffile;
		rm -f $tempfile;
		echo "New Instance ID: $newinstanceid";
	else
		errmsg="Instance ID is already defined in: $conffile - use '-f' to force change";
		error;
	fi
else
	echo "Instance ID does not exist";
	cp -f $conffile $confdir/pre14.0-`date -I`-conf.php;
	tempfile='/tmp/old.schoolbox.conf.php';
	tline=`grep -n "?>" $conffile | cut -d':' -f1`;
	pline=`grep -n "?>" $conffile | cut -d':' -f1 | wc -l`;
	wcfile=`wc -l $conffile | awk {'print $1'}`;
	head -n `expr $tline - 1` $conffile > $tempfile;
	echo "" >> $tempfile;
	echo "// Instance ID" >> $tempfile;
	echo "define('INSTANCE_ID', '$newinstanceid');" >> $tempfile;
	echo "" >> $tempfile;
	tail -n `expr $wcfile - $tline + 1` $conffile >> $tempfile;
	cp -f $tempfile $conffile;
	rm -f $tempfile;
	echo "New Instance ID: $newinstanceid";
fi
