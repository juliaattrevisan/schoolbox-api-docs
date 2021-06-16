#!/bin/bash

## Initial directory is where all the resources that are linked are included so save it for later use.
initialdir=`pwd`;

PHP_VERSION=$1

## Open up the datasets that we pulled in.
tar xzf sales-dataset/sales.tar.gz
tar xzf qa-container-config/qa-container-config.tar.gz

## As I am using the vagrant image we need to set things up in root directory.
cd /
CONFIG_DIR="/etc/php/7.4/fpm"
PHP_SERVICE="php7.4-fpm"

## Start database to initialise with a username and password
service mysql start

## Initialise database
mysql -e "create database schoolbox;"
mysql -e "create user 'schoolboxvagrant'@'%' IDENTIFIED BY 'abc123';"
mysql -e "grant all privileges on schoolbox.* to 'schoolboxvagrant'@'%';"

# initialise the data itself
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql -pPassw0rd!
mysql schoolbox < $initialdir/configfiles/vagrant_config.sql

## Make directories that will be used by our schoolbox instance
mkdir vagrant
mkdir mnt/data
mkdir mnt/data/php_tmp
mkdir vagrant/data
chmod 777 mnt/data/php_tmp

## Move our specific config items into the correct folders
cp -rf $initialdir/schoolbox-develop/* /vagrant
cp -f $initialdir/configfiles/dhparams.pem /etc/dhparams.pem
cp -rf $initialdir/configfiles/nginx/* /etc/nginx
cp -f $initialdir/configfiles/update-sql-no-prereq-test.sh /vagrant/tools/update-sql-nocheck.sh
cp -rf $initialdir/configfiles/php/7.1/fpm/* $CONFIG_DIR
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
export INSTANCE_ID=smoketest

## Currently, all smoke tests run on develop only.
## Therefore, we don't need to infer or inform which build to use: we can just
## use the develop build.
tar xzf $initialdir/build-artifact/develop-build.tar.gz;

## Extract the develop vendor build too
tar xzf $initialdir/vendor-php-artifact-dev/develop-vendor-php-dev.tar.gz;

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
service mysql start
echo 'started mysql'

## Go to the tools and update the db to be the latest version.
cd /vagrant/tools

./build-sql-update.sh
./update-sql-nocheck.sh

## Finish where we started.
cd $initialdir
