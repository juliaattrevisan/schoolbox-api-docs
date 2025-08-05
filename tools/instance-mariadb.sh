#!/usr/bin/env bash

# This script is designed to simply invoke the `mariadb` executable, with the
# credentials for whatever instance is in INSTANCE_ID.
# It works with:
# * interactive shell (no opts)
# * executing a command directly (-e "command")
# * piping a SQL file from stdin (< file)

declare sql_user
declare sql_pass
declare sql_host
declare sql_db

sql_user=$(schoolbox-read-instances --option SQL_user);
sql_pass=$(schoolbox-read-instances --option SQL_passwd);
sql_host=$(schoolbox-read-instances --option SQL_host);
sql_db=$(schoolbox-read-instances --option SQL_db);

mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" -D "$sql_db" "$@" < /dev/stdin
