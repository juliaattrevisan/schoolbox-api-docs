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

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

curdir=`pwd`;

# Ensure npm is installed
checknpm=`whereis -b npm | awk '{ print $2}'`;
if [ -z $checknpm ];
then
	echo "NPM is not installed. Please install npm via your package utilities.";
	exit 1;
fi

# Ensure less is installed
mkdir -p ~/node_modules;
cd ~;
npm install less@1.3.0 ~;
if [ ! -f ~/node_modules/.bin/lessc ];
then
	echo "Error: lessc not installed as expected";
	exit 1;
fi
cd $curdir;

# Build core.css
echo "Building core.css";
~/node_modules/.bin/lessc $instdir/www/css/core/core.less > $instdir/www/css/core/core.css;
echo "/* less (`~/node_modules/.bin/lessc -v | cut -d' ' -f2`) build at `date +%Y-%m-%d\ %H:%M:%S` */" >> $instdir/www/css/core/core.css;

# Build screen.css for each template
for i in `ls -1 $instdir/www/css/*/screen.less`;
do
        filepath=`readlink -f $i`;
        fullpath=${filepath%/*};
        template=${fullpath##*/};
        newfile="$fullpath/screen.css";
        echo "Building $template screen css";
        ~/node_modules/.bin/lessc $filepath > $newfile;
        echo "/* less (`~/node_modules/.bin/lessc -v | cut -d' ' -f2`) build at `date +%Y-%m-%d\ %H:%M:%S` */" >> $newfile;
done

# Build mchrome.css for each template
for i in `ls -1 $instdir/www/css/*/mchrome.less`;
do
	filepath=`readlink -f $i`;
	fullpath=${filepath%/*};
	template=${fullpath##*/};
	newfile="$fullpath/mchrome.css";
	echo "Building $template mobile css";
	~/node_modules/.bin/lessc $filepath > $newfile;
	echo "/* less (`~/node_modules/.bin/lessc -v | cut -d' ' -f2`) build at `date +%Y-%m-%d\ %H:%M:%S` */" >> $newfile;
done

# End of script
