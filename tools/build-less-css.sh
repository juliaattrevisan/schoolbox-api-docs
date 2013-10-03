#!/bin/bash
# Build CSS file from less versions
# Description:
#       Creates css files from less files for core template and mobile templates
#
# Usage:
#       Run automatically as part of the build/compile process
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

lessversion='1.3.3'

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

curdir=`pwd`;

verbose=0;
if [ ! -z $1 ];
then
	if [ $1 == '-v' ];
	then
		verbose=1;
	fi
fi

# Ensure npm is installed
checknpm=`whereis -b npm | awk '{ print $2}'`;
if [ -z $checknpm ];
then
	echo "NPM is not installed. Please install npm via your package utilities.";
	exit 1;
fi

# Ensure less is installed
if [ "`~/node_modules/.bin/lessc -v 2>/dev/null| awk {'print $2'}`" != "$lessversion" ];
then
	mkdir -p ~/node_modules;
	cd ~;
	npm install less@$lessversion ~;
	if [ ! -f ~/node_modules/.bin/lessc ];
	then
		echo "Error: lessc not installed as expected";
		exit 1;
	fi
	cd $curdir;
fi

# Build chrome.css for each template (except core)
for i in `ls -1 $instdir/www/css/*/chrome.less | grep -v 'core/chrome.less'`;
do
	filepath=`readlink -f $i`;
	fullpath=${filepath%/*};
	template=${fullpath##*/};
	newfile="$fullpath/chrome.css";
	# De-parallelize build of chrome.less, because it's faster to build in serial
	~/node_modules/.bin/lessc $filepath > $newfile
	echo "/* less ($lessversion) build at `date +%Y-%m-%d\ %H:%M:%S` */" >> $newfile && if [ $verbose -eq 1 ];then echo "$template - screen css";fi &
done

# Build mchrome.css for each template
for i in `ls -1 $instdir/www/css/*/mchrome.less | grep -v 'core/mchrome.less'`;
do
	filepath=`readlink -f $i`;
	fullpath=${filepath%/*};
	template=${fullpath##*/};
	newfile="$fullpath/mchrome.css";
	~/node_modules/.bin/lessc $filepath > $newfile && echo "/* less ($lessversion) build at `date +%Y-%m-%d\ %H:%M:%S` */" >> $newfile && if [ $verbose -eq 1 ];then echo "$template - mobile css";fi &
done
