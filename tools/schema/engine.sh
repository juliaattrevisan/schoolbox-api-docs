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
sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);

# select all tables that are MyISAM
QUERY="SELECT CONCAT_WS('.', '$sqldb', TABLE_NAME) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$sqldb' AND ENGINE = 'MyISAM'";
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "ENGINE=INNODB; SET foreign_key_checks = 1; "}'

# log intention
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST"

# execute
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST" | mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb
