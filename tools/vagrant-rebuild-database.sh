#!/bin/bash
# Rebuild Database
# Description:
#    Rebuilds database with latest data set. (copied directly out of vagrant
#    provision script)
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au

vag="[schoolbox]";

cachedir='/vagrant/vagrantcache';

echo "$vag Removing current Schoolbox Data directory";
rm /vagrant/data/* -fR;

echo "$vag Creating Schoolbox data directories";
cd /vagrant;
tar -xzf $cachedir/schoolboxdemo-latest.tar.gz;
rm -rf /vagrant/data/cache;
cd /vagrant/tools;
./create-data-dirs.sh /vagrant/data;

echo "$vag Updating timezone data";
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -ptnp879 mysql;

echo "$vag Removing current Schoolbox MySQL database";
mysql -u root -ptnp879 -e "drop database if exists schoolbox";

echo "$vag Loading Schoolbox MySQL Demo data";
mysql -u root -ptnp879 -e "create database schoolbox";
mysql -u root -ptnp879 schoolbox < $cachedir/schoolboxdemo-latest.sql

echo "$vag Generating data dir sql fixes";
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> $cachedir/schoolboxdemo-vagrant-latest.sql;

echo "$vag Applying vagrant SQL fixes";
mysql -u root -ptnp879 schoolbox < $cachedir/schoolboxdemo-vagrant-latest.sql

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
/vagrant/tools/schema/collation.sh schoolbox;

# Need to re-enable modules too, because they're off by default
echo "$vag Enabling modules";
/vagrant/tools/module.sh enable leave;
/vagrant/tools/module.sh enable resource;
