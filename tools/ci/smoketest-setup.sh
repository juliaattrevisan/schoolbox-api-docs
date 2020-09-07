#!/bin/bash

initialdir=`pwd`;

tar xzf sales-dataset/sales.tar.gz
tar xzf qa-container-config/qa-container-config.tar.gz

cd /

cat /etc/os-release

groupadd nginx
useradd -g nginx nginx
mkdir /var/lib/schoolbox
apt update
apt install git gzip -y
apt install php7.1-fpm php7.1-pdo php7.1-mysql nginx -y
apt install redis-server mysql-client grunt npm -y
npm install grunt
apt install mariadb-server -y
# latest build should have been pulled down
# configfiles.tar.gz should exist for us to use
# sales dataset should exist
# vagrant_config.sql should exist in config files
# start and initialise the database with a user, password, user ect.
service mysql start

mysql -e "create database schoolbox;"
mysql -e "create user 'schoolboxvagrant'@'%' IDENTIFIED BY 'abc123';"
mysql -e "grant all privileges on schoolbox.* to 'schoolboxvagrant'@'%';"

# initialise the data itself
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql -pPassw0rd!
mysql schoolbox < $initialdir/configfiles/vagrant_config.sql
mysql schoolbox -e "select value from config where name='site_version';"

mkdir vagrant
mkdir mnt/data
mkdir mnt/data/php_tmp
mkdir vagrant/data
chmod 777 vagrant/data
chmod 777 mnt/data/php_tmp

cp -rf $initialdir/schoolbox-develop/* /vagrant
cp -f $initialdir/configfiles/dhparams.pem /etc/dhparams.pem
cp -rf $initialdir/configfiles/nginx/* /etc/nginx
cp -f $initialdir/configfiles/update-sql-no-prereq-test.sh /vagrant/tools/update-sql-nocheck.sh
cp -rf $initialdir/configfiles/php/7.1/fpm/* /etc/php/7.1/fpm
cp -f $initialdir/configfiles/redis/redis.conf /etc/redis/redis.conf
cp -f $initialdir/configfiles/www-conf/conf.php /vagrant/www/conf/conf.php
cp -rf $initialdir/data/* /vagrant/data
cp -rf $initialdir/configfiles/bin/* /usr/local/bin
cd /vagrant

composer install
npm install
grunt build

chmod 777 -R /vagrant/data/*

echo '127.0.0.1 vagrant.dev.schoolbox.cloud' >> /etc/hosts

service php7.1-fpm start
echo 'started php'
service nginx start
echo 'started nginx'
service redis-server start
echo 'started redis-server'
service mysql start
echo 'started mysql'

cd /vagrant/tools

./build-sql-update.sh
./update-sql-nocheck.sh

cd ../
