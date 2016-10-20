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

# ensure the DB itself is defaulting to UTF8
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "ALTER DATABASE `$sqldb` CHARACTER SET utf8 COLLATE utf8_general_ci; "

# select all tables that are UTF8, but are NOT collated 'general'
QUERY="SELECT t.TABLE_NAME FROM INFORMATION_SCHEMA.TABLES t LEFT JOIN INFORMATION_SCHEMA.COLUMNS c ON c.TABLE_NAME = t.TABLE_NAME AND c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.COLLATION_NAME IS NOT NULL WHERE t.TABLE_SCHEMA = '$sqldb' AND t.TABLE_COLLATION NOT LIKE 'utf8%' AND c.COLUMN_NAME IS NOT NULL"
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "CHARSET=utf8 COLLATE=utf8_general_ci; SET foreign_key_checks = 1; "}'

# log intention
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST"

# execute
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST" | mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb


# now doublecheck for any remnant non-default fields
QUERY="SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$sqldb' AND COLLATION_NAME NOT LIKE 'utf8%'"
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "MODIFY", $2, $3, "CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1; "}'

# log intention
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST"

# execute
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST" | mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb
