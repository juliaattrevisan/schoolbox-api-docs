#!/bin/bash
# Executes a command for each instance in a sequential loop.
# Usage:
#   ./each-instance.sh $command $args

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"


# Global constants
readonly LOG="/usr/bin/alaress-logger"
readonly L_INFO=200;

# Log an informational message
function log(){
  $LOG $L_INFO "[SB] $1"
}

# Find the conf file relative to this file
file_path=$(readlink -f "$0");
dir_path=${file_path%/*}
conf_file=$(readlink -f "$dir_path"/../www/conf/conf.php);

# Derive the list of instance's in this package
instance_ids=$(schoolbox-conf --conf="$conf_file" --option=instancelist | jq -r '.[]' | sort);
mapfile -t instance_ids_array <<< "$instance_ids"
count=${#instance_ids_array[@]}
for instance_id in $instance_ids; do
  ((idx+=1))
  log "$idx/$count, INSTANCE_ID=$instance_id"
  INSTANCE_ID=$instance_id "$@"
  if [[ $idx != "$count" ]]; then
    echo -e "\n\n"
  fi
done

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
