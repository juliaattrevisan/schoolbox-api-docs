#!/bin/sh
DB=$1

if [ $# -ne 1 ]; then
    echo "Specify database";
    exit 1;
fi

# select all tables that are UTF8, but are NOT collated 'general'
QUERY="SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$DB' AND TABLE_COLLATION != 'utf8_general_ci' AND TABLE_COLLATION LIKE 'utf8%'"
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1; "}'

# log intention
mysql --database=$DB -B -N -e "$QUERY" | awk "$AWKST"

# execute
mysql --database=$DB -B -N -e "$QUERY" | awk "$AWKST" | mysql --database=$DB


# now doublecheck for any remnant non-default fields
QUERY="SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$DB' AND COLLATION_NAME != 'utf8_general_ci' AND COLLATION_NAME LIKE 'utf8%'"
AWKST='{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "MODIFY", $2, $3, "CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1; "}'

# log intention
mysql --database=$DB -B -N -e "$QUERY" | awk "$AWKST"

# execute
mysql --database=$DB -B -N -e "$QUERY" | awk "$AWKST" | mysql --database=$DB
