#!/bin/bash

## Initial directory is where all the resources that are linked are included so save it for later use.
initialdir=`pwd`;
filepath=`readlink -f $0`;
fullpath=${filepath%/*}

PHP_VERSION=$1

## Open up the datasets that we pulled in.
tar xzf sales-dataset/sales.tar.gz
tar xzf qa-container-config/qa-container-config.tar.gz

## As I am using the vagrant image we need to set things up in root directory.
cd /
CONFIG_DIR="/etc/php/7.4/fpm"
PHP_SERVICE="php7.4-fpm"

## Start database to initialise with a username and password
service mariadb start

## Initialise database
mariadb -e "create database schoolbox;"
mariadb -e "create user 'schoolboxvagrant'@'%' IDENTIFIED BY 'abc123';"
mariadb -e "grant all privileges on schoolbox.* to 'schoolboxvagrant'@'%';"

# initialise the data itself
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql -pPassw0rd!
mysql schoolbox < $initialdir/data/schoolbox.sql
mysql schoolbox -e "UPDATE config SET value = REPLACE(value, '/var/lib/schoolbox', '/vagrant/data') WHERE value like '/var/lib/schoolbox%'"
mysql schoolbox -e "UPDATE user SET password = '*5A552EC460D7C517B5C9EFDBC8F2C762B4D923CB' WHERE username like 'alaress'"
mysql schoolbox -e "UPDATE config SET value='1',editable='true' WHERE name IN ('boarder_leave_enabled','resource_booking_enabled','push_notifications_enabled','pastoral_enabled','ups_groups_enabled','eotc_enabled','fixtures_enabled');"


## Make directories that will be used by our schoolbox instance
mkdir vagrant
mkdir mnt/data
mkdir mnt/data/php_tmp
mkdir vagrant/data
ln -sf /vagrant/data /var/lib/schoolbox
chmod 777 mnt/data/php_tmp

## Move our specific config items into the correct folders
cp -rf $fullpath/../../* /vagrant
cp -f $initialdir/configfiles/dhparams.pem /etc/dhparams.pem
cp -rf $initialdir/configfiles/nginx/* /etc/nginx
cp -f $initialdir/configfiles/update-sql-no-prereq-test.sh /vagrant/tools/update-sql-nocheck.sh
cp -rf $initialdir/configfiles/php/7.4/fpm/* $CONFIG_DIR
cp -f $initialdir/configfiles/redis/redis.conf /etc/redis/redis.conf
cp -f $initialdir/configfiles/www-conf/conf.php /vagrant/www/conf/conf.php
cp -rf $initialdir/data/* /vagrant/data
cp -rf $initialdir/configfiles/bin/* /usr/local/bin

## Go to vagrant to initialise npm and composer
cd /vagrant

echo "Deploying schoolbox config files"
mkdir -p /etc/schoolbox
cp tools/ci/etc/config.ini /etc/schoolbox/config.ini
cp tools/ci/etc/instances.ini /etc/schoolbox/instances.ini
tools/set-plan.php --plan=elite
export INSTANCE_ID=smoketest

# Do not extract the build artifact: it clobbers what's in the PR
#tar xzf $initialdir/build-artifact/*-build.tar.gz;

## Extract the NPM build
tar xzf $initialdir/assets-artifact/*-assets.tar.gz;

## Extract the develop vendor build too
tar xzf $initialdir/vendor-php-artifact-dev/*-vendor-php-dev.tar.gz;

## Make sure the data directory has no permissions issues.
chmod 777 -R /vagrant/data/*

## Push out a dns record to the hosts file to ensure all the linkage works properly
echo '127.0.0.1 vagrant.dev.schoolbox.cloud' >> /etc/hosts

## Start the services!
service $PHP_SERVICE start
echo 'started php'
service nginx start
echo 'started nginx'
service redis-server start
echo 'started redis-server'
service mariadb start
echo 'started mariadb'

## Go to the tools and update the db to be the latest version.
cd /vagrant/tools

./build-sql-update.sh
./update-sql-nocheck.sh

## Finish where we started.
cd $initialdir
