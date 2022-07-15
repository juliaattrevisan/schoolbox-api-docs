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

cachedir='/usr/share/schoolbox/vagrantcache';

echo "$vag Removing current Schoolbox Data directory";
rm /var/lib/schoolbox/* -fR;

echo "$vag Creating Schoolbox data directories";
cd /usr/share/schoolbox;
tar -xzf $cachedir/schoolboxdemo-v6.1.tar.gz;
rm -rf /var/lib/schoolbox/cache;
cd /usr/share/schoolbox/tools;
./create-data-dirs.sh /var/lib/schoolbox;

echo "$vag Updating timezone data";
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -ptnp879 mysql;

echo "$vag Removing current Schoolbox MySQL database";
mysql -u root -ptnp879 -e "SET MAX_STATEMENT_TIME=0; DROP DATABASE IF EXISTS schoolbox";

echo "$vag Loading Schoolbox MySQL Demo data";
mysql -u root -ptnp879 -e "CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';";
(echo "SET MAX_STATEMENT_TIME=0; "; cat $cachedir/schoolboxdemo-v6.1.sql) | mysql -u root -ptnp879 schoolbox

echo "$vag Generating data dir sql fixes";
/usr/share/schoolbox/tools/generate-dir-sql.sh /var/lib/schoolbox >> $cachedir/schoolboxdemo-vagrant-v6.1.sql;

echo "$vag Applying vagrant SQL fixes";
mysql -u root -ptnp879 schoolbox < $cachedir/schoolboxdemo-vagrant-v6.1.sql

echo "$vag Building SQL updates";
cd /usr/share/schoolbox/tools;
./build-sql-update.sh;

#cd /usr/share/schoolbox;
echo "$vag Upgrading demo data with latest SQL updates";
mkdir /var/lib/vagrant -p > /dev/null;
/usr/share/schoolbox/tools/update-sql.sh;
rm -f /usr/share/schoolbox/updates/*/scripts_list > /dev/null;
rm -vR /var/lib/vagrant > /dev/null;

echo "$vag Applying UTF8 schema patch";
/usr/share/schoolbox/tools/schema/collation2.sh schoolbox;
