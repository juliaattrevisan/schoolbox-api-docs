#!/bin/bash
# Schoolbox v14.0+ Sandbox Prebuild Script
# Description:
#       Creates a prebuilt sandbox for use in building sandboxes.
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

echo "Schoolbox v14.0+ Sandbox Pre-builder";
echo "";

echo -e "Release tag to build: \c" && read tag;
if [ -z $tag ];
then
        echo "Error: a release tag must be supplied";
        exit 1;
fi

releases='/home/vagrant/release-prebuild';
mkdir -p $releases;

if [ ! -f $releases/$tag.tar.gz ];
then
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
        echo "Downloading release archive: $tag";
        curl -L --user "$githubuser:$githubpass" https://github.com/alaress/schoolbox/archive/$tag.tar.gz > $releases/$tag.tar.gz
        if [ ! -f $releases/$tag.tar.gz ];
        then
                echo "Error: tag archive download failed";
                exit 1;
        fi
fi

tempdir="/tmp/prebuilder";
rm -fR $tempdir;
mkdir -p $tempdir;
cd $tempdir;

# Check if tag is already pre-built, if not - build it
if [ ! -f $releases/$tag-prebuilt.tar.gz ];
then
        echo "Extracting release archive";
        tar -xzf $releases/$tag.tar.gz;
        if [ $? -ne 0 ] || [ ! -d "schoolbox-$tag" ];
        then
                echo "Error: archive did not extract in the expected way";
                exit 1;
        fi

        cd $tempdir/schoolbox-$tag;

        # Remove non-core/demo skins
        for i in `ls -1 templates | grep -v 'core' | grep -v 'demo'`;
        do
                if [ "$i" != "m" ];
                then
                        rm -vfR templates/$i;
                        rm -vfR www/*/$i;
                fi
        done;

        # Install submodules using composer
        tools/composer.sh --no-dev;
        # Install grunt
        npm cache clean;
        npm install grunt-cli;
        # Build and install CSS and JS using grunt/bower
        tools/pkg-build-css-js.sh;
        # Cleanup live vs dev files and information
        tools/pkg-live-vs-dev.sh schoolbox;
        # Auto build the sql update scripts
        $tempdir/schoolbox-$tag/tools/build-sql-update.sh;

        # Remove non-deployable files and folders
        rm -fR $tempdir/schoolbox-$tag/behat.yml;
        rm -fR $tempdir/schoolbox-$tag/bower.json;
        rm -fR $tempdir/schoolbox-$tag/composer.*;
        rm -fR $tempdir/schoolbox-$tag/Gruntfile.js;
        rm -fR $tempdir/schoolbox-$tag/node_modules;
        rm -fR $tempdir/schoolbox-$tag/package.json;
        rm -fR $tempdir/schoolbox-$tag/phpunit.xml;
        rm -fR $tempdir/schoolbox-$tag/README.md;
        rm -fR $tempdir/schoolbox-$tag/*.log;
        rm -fR $tempdir/schoolbox-$tag/test;
        rm -fR $tempdir/schoolbox-$tag/tools/vagrant*;
        rm -fR $tempdir/schoolbox-$tag/tools/pkg-*;
        rm -fR $tempdir/schoolbox-$tag/composer*;
        rm -fR $tempdir/schoolbox-$tag/tools/git*;
        rm -fR $tempdir/schoolbox-$tag/www/test;
        rm -fR $tempdir/schoolbox-$tag/Vagrantfile;
        rm -fR $tempdir/schoolbox-$tag/www/static/javascript/components/*/node_modules;
        # Remove as not a deb build
        rm -fR $tempdir/schoolbox-$tag/debian;

        cd $tempdir;
        tar czf $releases/$tag-prebuilt.tar.gz schoolbox-$tag;

else
        cd $tempdir;
        tar xzf $releases/$tag-prebuilt.tar.gz;
        cd $tempdir/schoolbox-$tag;
fi
echo "Release $tag built or extracted";

rm -fR $tempdir;
