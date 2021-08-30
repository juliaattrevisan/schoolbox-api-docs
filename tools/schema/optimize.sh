#!/bin/bash
# Perform mysql optimize for a single instance
# Usage:
#  INSTANCE_ID=xxxxxxxx tools/schema/optimize.sh

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;

echo "Start at: $(date)"

file_path=$(readlink -f "$0");
dir_path=${file_path%/*};
site_root=$(readlink -f "$dir_path"/../../);
package=${site_root##*/};
if [[ $package == 'vagrant' ]]; then
  package=schoolbox
fi
ini_file=/etc/$package/instances.ini

if ! db_user=$(schoolbox-read-instances --ini "$ini_file" --option SQL_user) ||
   ! db_pass=$(schoolbox-read-instances --ini "$ini_file" --option SQL_passwd) ||
   ! db_host=$(schoolbox-read-instances --ini "$ini_file" --option SQL_host) ||
   ! db_schema=$(schoolbox-read-instances --ini "$ini_file" --option SQL_db) ||
   [[ -z $db_user ]] ||
   [[ -z $db_pass ]] ||
   [[ -z $db_host ]] ||
   [[ -z $db_schema ]]; then
  echo "Unable to derive SQL credentials" >&2
  exit 1
fi

mysqlcheck --optimize -h"$db_host" -u"$db_user" -p"$db_pass" "$db_schema"

echo "Finished at: $(date)"
