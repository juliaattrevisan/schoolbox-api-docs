#!/bin/bash
# Generate Directory SQL Script
# Description:
#   Generates the SQL required to update the Schoolbox config table with the locations of the data directories.
#   The result is sent to STDOUT
# Usage:
#   INSTANCE_ID=XXX generate-dir-sql-dynamically.sh [INSTANCE_DATA_DIR]
#
# @copyright   Copyright 2022, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

readonly COLOUR_RED="\033[38;5;1m";
readonly COLOUR_DEFAULT="\033[39m";

if [ $# -ne 1 ]; then
  echo -e "${COLOUR_RED}Invalid parameters detected${COLOUR_DEFAULT}" >&2
  echo -e "${COLOUR_RED}Please provide the instance's data dir, eg${COLOUR_DEFAULT}" >&2
  echo -e "${COLOUR_RED}    INSTANCE_ID=a1b2c3d4 $0 /var/lib/schoolbox/a1b2c3d4${COLOUR_DEFAULT}" >&2
  exit 1
fi

# Strip the trailing slash if present, eg '/var/lib/schoolbox/a1b2c3d4/'
# shellcheck disable=SC2001
instance_data_dir="$(echo "$1" | sed 's:/*$::')"

# Derive SQL credentials
sql_host=$(schoolbox-read-instances --option SQL_host)
sql_user=$(schoolbox-read-instances --option SQL_user)
sql_pass=$(schoolbox-read-instances --option SQL_passwd)
sql_name=$(schoolbox-read-instances --option SQL_db)
if [[ -z $sql_host ]] || [[ -z $sql_user ]] || [[ -z $sql_pass ]] || [[ -z $sql_name ]]; then
  echo -e "${COLOUR_RED}Unable to derive SQL credentials${COLOUR_DEFAULT}" >&2
  exit 1
fi

# Fetch data dir definitions from DB
data_dirs_config=$(mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_name" -ss -e "SELECT value from config WHERE name = 'data_dirs_defaults'")
IFS=';' read -r -a array <<< "$data_dirs_config"

# While technically we can probably share some of these directories across all instances on a site,
# for simplicity and safety for now we will make them all instance-specific.
# If we do wish to share some dirs across the site, this script will need to accept two args [SITE_DATA_DIR], [INSTANCE_ID]
for element in "${array[@]}"; do
  IFS=':' read -r -a sub_array <<< "$element"
  data_dir="${sub_array[0]}"
  path="${sub_array[1]}"
  echo "UPDATE config SET value = '$instance_data_dir/$path' WHERE name = '$data_dir';"
done
