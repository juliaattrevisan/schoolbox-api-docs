#!/bin/bash
# Rebuild Database
# Description:
#    Rebuilds database with latest data set. (copied directly out of vagrant
#    provision script)
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

vag="[schoolbox]";

cachedir='/vagrant/vagrantcache';

echo "$vag Removing current Schoolbox Data directory";
rm /vagrant/data/* -fR;

echo "$vag Creating Schoolbox data directories";
cd /vagrant;
tar -xzf $cachedir/schoolboxdemo-v6.1.tar.gz;
rm -rf /vagrant/data/cache;
cd /vagrant/tools;
./create-data-dirs.sh /vagrant/data;

echo "$vag Updating timezone data";
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -ptnp879 mysql;

echo "$vag Removing current Schoolbox MySQL database";
mysql -u root -ptnp879 -e "drop database if exists schoolbox";

echo "$vag Loading Schoolbox MySQL Demo data";
mysql -u root -ptnp879 -e "CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';";
mysql -u root -ptnp879 schoolbox < $cachedir/schoolboxdemo-v6.1.sql

echo "$vag Generating data dir sql fixes";
/vagrant/tools/generate-dir-sql.sh /vagrant/data >> $cachedir/schoolboxdemo-vagrant-v6.1.sql;

echo "$vag Applying vagrant SQL fixes";
mysql -u root -ptnp879 schoolbox < $cachedir/schoolboxdemo-vagrant-v6.1.sql

echo "$vag Building SQL updates";
cd /vagrant/tools;
./build-sql-update.sh;

#cd /vagrant;
echo "$vag Upgrading demo data with latest SQL updates";
mkdir /var/lib/vagrant -p > /dev/null;
/vagrant/tools/update-sql.sh;
rm -f /vagrant/updates/*/scripts_list > /dev/null;
rm -vR /var/lib/vagrant > /dev/null;

echo "$vag Applying UTF8 schema patch";
/vagrant/tools/schema/collation2.sh schoolbox;
