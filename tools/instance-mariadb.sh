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

if [ ! -v INSTANCE_ID ]; then
  echo "You must provide an INSTANCE_ID env var, either:" >&2
  echo "* for this command only:            \`INSTANCE_ID=xxx $0\`" >&2
  echo "* for all commands in this session: \`export INSTANCE_ID=xxx\`" >&2
  echo "" >&2
  exit 1
fi

sql_user=$(schoolbox-read-instances --option SQL_user);
sql_pass=$(schoolbox-read-instances --option SQL_passwd);
sql_host=$(schoolbox-read-instances --option SQL_host);
sql_db=$(schoolbox-read-instances --option SQL_db);

mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" -D "$sql_db" "$@" < /dev/stdin
