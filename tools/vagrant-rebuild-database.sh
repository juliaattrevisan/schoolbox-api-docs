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

echo "$vag Removing current Schoolbox MySQL database";
mysql -u root -ptnp879 -e "drop database if exists schoolbox";

echo "$vag Loading Schoolbox MySQL Demo data";
mysql -u root -ptnp879 -e "create database schoolbox";
mysql -u root -ptnp879 schoolbox < /home/vagrant/schoolboxdemo-latest.sql

echo "$vag Building SQL updates";
cd /vagrant/tools;
./build-sql-update.sh;

#cd /vagrant;
echo "$vag Upgrading demo data with latest SQL updates";
mkdir /var/lib/vagrant -p > /dev/null;
/vagrant/tools/update-sql.sh;
rm -f /vagrant/updates/*/scripts_list > /dev/null;
rm -vR /var/lib/vagrant > /dev/null;

echo "$vag Generating data dir sql fixes";
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /home/vagrant/schoolboxdemo-vagrant-latest.sql;

echo "$vag Applying vagrant SQL fixes";
mysql -u root -ptnp879 schoolbox < /home/vagrant/schoolboxdemo-vagrant-latest.sql
