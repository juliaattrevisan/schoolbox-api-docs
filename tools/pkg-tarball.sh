#!/bin/bash
# Tarball Package Build Script
# Description:
#       Downloads a specifc tagged release, and packages into a .tgz archive
#       for deployment. Uploads compiled release to server on completion.
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

curdir=`pwd`;

# Create temp build directory
thistempdir="/tmp/schoolbox-`date -I`";
rm -rf $thistempdir;
mkdir -p $thistempdir;
cd $thistempdir;

echo "Schoolbox Tarball Package Build Scri[t";

# provide a tag to build
echo -e "Tag to build: \c" && read tag;
if [ -z $tag ];
then
	echo "Error: a release tag must be supplied";
	exit 1;
fi

# provide a github username
echo -e "GitHub Username: \c" && read githubuser;
if [ -z $githubuser ];
then
	echo "Error: a github username must be supplied";
	exit 1;
fi

# provide a github password
echo -e "GitHub Password: \c" && read -s githubpass;
if [ -z $githubpass ];
then
	echo "Error: a github password must be supplied";
	exit 1;
fi

echo "";
echo "Downloading tag archive for release $tag";
curl -L --progress-bar --user "$githubuser:$githubpass" https://github.com/alaress/schoolbox/archive/$tag.tar.gz > $tag.tar.gz
#wget --no-check-certificate https://$githubuser:$githubpass@github.com/alaress/schoolbox/archive/$tag.tar.gz
if [ ! -f $tag.tar.gz ];
then
	echo "Error: tag archive download failed";
	exit 1;
fi

echo "Extracting tag archive";
tar -xzf $tag.tar.gz
if [ ! -d "schoolbox-$tag" ];
then
	echo "Error: archive did not extract in the expected way";
	exit 1;
fi

# cd into dir
cd schoolbox-$tag

echo "Creating data directories (ignore the permission/user error)";
mkdir -p data
tools/create-data-dirs.sh data

echo "Building update scripts";
cd tools
./build-sql-update.sh

echo "Ensuring correct version of lessc is installed";
cd ~;
mkdir -p ~/node_modules
npm install less@1.3.0 ~
if [ ! -f ~/node_modules/.bin/lessc ];
then
	echo "Error: lessc not installed as expected";
	exit 1;
fi

echo "Building core.css with lessc";
cd $thistempdir/schoolbox-$tag;
~/node_modules/.bin/lessc www/css/core/core.less > www/css/core/core.css
echo "/* less (`~/node_modules/.bin/lessc -v | cut -d' ' -f2`) build at `date +%Y-%m-%d\ %H:%M:%S` */" >> www/css/core/core.css

echo "Removing unrequired files and directories";
rm -rf {debian,phpunit.xml,README.md,test,.git*}

echo "Packaging Release into .tgz";
tar -czf $thistempdir/release_$tag.tgz *
cd $thistempdir;

echo "Uploading release to server";
scp release_$tag.tgz schoolbo@schoolbox.com.au:/home/schoolbo/www/schoolboxinstaller;

echo "Deleting temporary files";
rm -rf $thistempdir;

cd $curdir;
