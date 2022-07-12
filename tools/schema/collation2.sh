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

# ensure the DB itself is defaulting to UTF8
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SET MAX_STATEMENT_TIME=0; ALTER DATABASE $sqldb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; "

# select all tables that are UTF8, but are NOT collated 'general'
QUERY="SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$sqldb' AND TABLE_COLLATION NOT LIKE 'utf8mb4_unicode_ci'"
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; SET foreign_key_checks = 1; "}'

# log intention
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST"

# execute
mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST" | mysql --defaults-extra-file=/etc/schoolbox/mariadb-no-max-statement-time.cnf -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb


# now doublecheck for any remnant non-default fields
#QUERY="SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$sqldb' AND CHARACTER_SET_NAME LIKE 'utf8'"
#AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "MODIFY", $2, $3, "CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; SET foreign_key_checks = 1; "}'

# log intention
#mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST"

# execute
#mysql -B -N -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "$QUERY" | awk "$AWKST" | mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb
