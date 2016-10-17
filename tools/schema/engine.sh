#!/bin/sh

filepath=`readlink -f $0`;
fullpath=${filepath%/*};
conffile=`readlink -f $fullpath/../../www/conf/conf.php`;

echo "Starting SQL collation update process";

if [ ! -f $conffile ];
then
    echo "New installation or conf file missing";
    exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;

# select all tables that are MyISAM
QUERY="SELECT CONCAT_WS('.', '$sqldb', TABLE_NAME) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$sqldb' AND ENGINE = 'MyISAM'";
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "ENGINE=INNODB; SET foreign_key_checks = 1; "}'

# log intention
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST"

# execute
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST" | mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb
