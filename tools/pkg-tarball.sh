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

echo "Schoolbox Tarball Package Build Script";

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

echo "Building css files from less";
cd $thistempdir/schoolbox-$tag;
tools/build-less-css.sh -v

echo "Install submodules using composer";
tools/composer.sh;

echo "Building directory sql updates";
tools/generate-dir-sql.sh /var/www/schoolbox/data /var/www/schoolbox > sql/schoolbox-package-config.sql

echo "Removing unrequired files and directories";
rm -rf {debian,phpunit.xml,README.md,test,.git*,Vagrantfile,tools/vagrant*,tools/pkg-*,composer*}

echo "Removing MGS Sport compatibility files";
rm -rf www/include/permission.php;

echo "Packaging Release into .tgz";
tar -czf $thistempdir/release_$tag.tgz *
cd $thistempdir;

echo "Uploading release to server";
scp release_$tag.tgz schoolbo@schoolbox.com.au:/home/schoolbo/www/schoolboxinstaller;

echo "Deleting temporary files";
rm -rf $thistempdir;

cd $curdir;
