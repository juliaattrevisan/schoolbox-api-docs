#!/bin/bash
# Refresh a cloud instance using an S3 dataset
#
# This should only be invoked on the primary node in a cluster.
#
# Usage:
#        INSTANCE_ID=[INSTANCE_ID] tools/cloud/data-refresh.sh \
#         --dataset [sales.tar.gz] \
#         --[CONFIG_NAME] [CONFIG_VALUE]
#         ...
# Example:
#       INSTANCE_ID=vagrantx /vagrant/tools/cloud/data-refresh.sh \
#         --dataset initial.tar.gz \
#         --site_hostname demo \
#         --amqp_host primary.mq.service.consul \
#         --redis_url tcp://primary.redis.service.consul:6379 \
#         --site_domain schoolbox.com.au \
#         --title 'Schoolbox Demo'


# Global const
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200;
readonly L_ERROR=400;
readonly ARG_DATASET='dataset'

# Global vars and defaults
declare dataset;     # eg 'initial.tar.gz'
declare site_root;
declare package;
declare sql_user;
declare sql_passwd;
declare sql_host;
declare sql_schema;
declare -A config;

# Config defaults. Empty values are required to be provided as CLI args
config[site_hostname]=""
config[site_domain]=""
config[title]=""
config[amqp_host]=""
config[amqp_vhost]=sbc_"$INSTANCE_ID"
config[amqp_user]=sbc_"$INSTANCE_ID"
config[amqp_pass]=password
config[redis_url]=""
config[mail_enable]=0
config[mail_smtp_host]=127.0.0.1
config[otfs_adapter]=awsS3
config[otfs_s3_presigned_urls_enabled]=1


function log(){
  $LOG $L_INFO "$1"
}

function error(){
  $LOG $L_ERROR "$1"
}

# Check scrips is running as root.
function check_run_as_root(){
  if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root"
    exit 1
  fi
}

function check_instance_id(){
  if [[ -z "$INSTANCE_ID" ]]; then
    error "You must set an INSTANCE_ID as environment var"
    exit 1
  fi
}


# Parse ENV + CLI flags
function parse_args(){
  local errors='0'
  local this_file_path;
  local this_file_dir;
  local conf_file;

  this_file_path=$(readlink -f "$0");
  this_file_dir=${this_file_path%/*};
  site_root=$(readlink -f "$this_file_dir"/../../);
  package=${site_root##*/};
  if [[ $package == '/vagrant' ]]; then
    package='schoolbox'
  fi
  conf_file=$(readlink -f "$site_root"/www/conf/conf.php);

  while [[ $# -gt 0 ]]; do
    case $1 in
      --$ARG_DATASET)
      dataset="$2"
      shift; # past argument
      shift; # past value
      ;;
      *)
      local recognised='0'
      for key in "${!config[@]}"; do
        if [[ "--$key" == "$1" ]]; then
          config[$key]="$2"
          recognised='1'
          shift # past argument
          shift # past value
          break
        fi
      done
      if [[ $recognised == '0' ]]; then
        error "Unrecognised option: '$1'"
        shift # past argument
        errors="1"
      fi
      ;;
    esac
  done

  if [[ -z "$dataset" ]]; then
    error "You must declare a dataset to import using the --$ARG_DATASET flag"
    errors="1"
  fi

  for key in "${!config[@]}"; do
    if [[ -z ${config[$key]} ]]; then
      error "You must declare a config value using the --$key flag"
      errors="1"
    fi
  done

  sql_user=$(schoolbox-conf --conf="$conf_file" --option=SQL_user);
  sql_passwd=$(schoolbox-conf --conf="$conf_file" --option=SQL_passwd);
  sql_host=$(schoolbox-conf --conf="$conf_file" --option=SQL_host);
  sql_schema=$(schoolbox-conf --conf="$conf_file" --option=SQL_db);

  if [[ -z $sql_user ]]; then
    error "Unable to derive SQL user"
    errors="1"
  fi
  if [[ -z $sql_passwd ]]; then
    error "Unable to derive SQL user"
    errors="1"
  fi
  if [[ -z $sql_host ]]; then
    error "Unable to derive SQL user"
    errors="1"
  fi
  if [[ -z $sql_schema ]]; then
    error "Unable to derive SQL user"
    errors="1"
  fi

  if [[ $errors == "1" ]]; then
    error "Validation errors occurred"
    exit 1
  fi
}


# Output a usage help message
function usage () {
  echo "
  Usage : INSTANCE_ID=<instance_id> $0 \
            --$ARG_DATASET=<dataset_file>";
}


## Setup working directory
function setup_working_dir(){
  rm -fR "$1";
  mkdir -p "$1";
}


## Extract dataset from provided location
function fetch_and_extract_dataset(){
  local working_dir=$1

  log "Fetching dataset '$dataset'"
  if ! "$site_root"/tools/download-dataset.sh --dataset "$dataset" --cache-dir /tmp; then
    error "Unable to fetch dataset"
    exit 1
  fi

  log "Extracting data to '$working_dir'"
  if ! tar -C "$working_dir" -xzf "/tmp/$dataset"; then
    error "Unable to extract dataset"
    exit 1
  fi
}


# Drop all database views and tables
function empty_schema(){
  log "Dropping all database views..."
  for db_view in $(mysql -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema" -ss -e 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE LIKE "VIEW"'); do
    if ! mysql -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema" -e "DROP view $db_view"; then
      error "Unable to drop database view: $db_view"
      error 1;
    fi
  done

  log "Dropping all database tables..."
  if ! mysqldump --add-drop-table --no-data -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema" | grep -e '^DROP \| FOREIGN_KEY_CHECKS' | mysql -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema"; then
    error "Unable to drop database tables"
    exit 1;
  fi
}


# Import the SQL dump file into the schema
function import_dataset(){
  local working_dir=$1

  log "Loading dataset into database schema '$sql_schema'..."
  if ! pv "$working_dir"/data/schoolbox.sql | sed -E "s/^\/\*\!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mysql -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema"; then
    error "Unable to import database"
    exit 1;
  fi
}


# Localise the database config table
function localise_database_config(){
  for key in "${!config[@]}"; do
    if ! mysql -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema" -e "UPDATE config SET value='${config[$key]}' WHERE name='$key'"; then
      error "Unable to set config '$key' to '${config[$key]}'"
      exit 1;
    fi
  done
}


# We need to place data dirs from inside the dataset into their canonical instance-specific locations
function create_data_dirs(){
  local instance_data_dir="/var/lib/$package/$INSTANCE_ID"

  if ! "$site_root"/tools/create-data-dirs.sh "$instance_data_dir"; then
    error "Unable to create data dirs"
    exit 1;
  fi
  if ! "$site_root"/tools/generate-dir-sql.sh "$instance_data_dir" | mysql -h "$sql_host" -u "$sql_user" -p"$sql_passwd" "$sql_schema"; then
    error "Unable to set data dirs in config table"
    exit 1;
  fi
  if ! "$site_root"/tools/set-data-dir-perms.sh "$instance_data_dir"; then
    error "Unable to set data dir ownership and perms"
    exit 1;
  fi
}


# Upgrade SQL
function upgrade_sql(){
  if ! "$site_root"/tools/update-sql.sh; then
    error "Unable to update SQL"
    exit 1;
  fi
}


# Wipe messages from redis
function empty_redis(){
  local redis_url;
  redis_url=$(echo "${config[redis_url]}" | sed -E "s/^[a-z]+:\/\///")
  local redis_host;
  local redis_port;
  redis_host=$(echo "$redis_url" | cut -d ":" -f 1)
  redis_port=$(echo "$redis_url" | cut -d ":" -f 2)
  if [[ -z $redis_port ]]; then
    redis_port=6379
  fi
  log "Resetting redis. Host: $redis_host, Port: $redis_port)"
  redis-cli -h "$redis_host" -p $redis_port KEYS "*:$INSTANCE_ID:*" | xargs redis-cli -h "$redis_host" -p $redis_port DEL;
}

# Wipe rabbitmq for this instance
function empty_rabbitmq(){
  log "Resetting rabbitmq - TODO"
  log "config[amqp_host]: ${config[amqp_host]}"
  log "config[amqp_vhost]: ${config[amqp_vhost]}"
  log "config[amqp_user]: ${config[amqp_user]}"
  log "config[amqp_pass]: ${config[amqp_pass]}"
  # TODO. This is tricky because we the rabbit server is remote
  #       rabbitmqadmin is a python utility that uses the rabbitmq HTTPAPI, but has a subset of functions.
  #       eg https://raw.githubusercontent.com/rabbitmq/rabbitmq-management/v3.8.8/bin/rabbitmqadmin
  #       eg rabbitmqadmin -H SERVER-IP -u USER_NAME -p PASSWORD list vhosts
  #
  #       rabbitmqctl is full featured, but requires us to have rabbitmq-server installed locally.

#  rabbitmqadmin -H SERVER-IP -u USER_NAME -p PASSWORD list vhosts
#  rabbitmqctl delete_vhost sbc_"$INSTANCE_ID";
#  rabbitmqctl add_vhost "${config[$key]}";
#  rabbitmqctl add_user sbc_"$INSTANCE_ID" password;
#  rabbitmqctl set_permissions -p sbc_"$INSTANCE_ID" sbc_"$INSTANCE_ID" ".*" ".*" ".*";
#  rabbitmqctl set_permissions -p sbc_"$INSTANCE_ID" guest ".*" ".*" ".*";
}


# Main logic flow
function main(){
  PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games';
  check_run_as_root
  check_instance_id
  parse_args "$@"

  # setup a working directory for our extracted dataset files
  local working_dir=/tmp/schoolbox-"$INSTANCE_ID"
  setup_working_dir "$working_dir"

  # fetch dataset from S3 into /tmp
  fetch_and_extract_dataset "$working_dir"

  # Import dataset SQL and upgrade to current version
  empty_schema
  import_dataset "$working_dir"
  localise_database_config
  upgrade_sql

  # Ensure data dirs exists with correct perms and ownership
  create_data_dirs

  # Clear transient data
  empty_redis
  empty_rabbitmq

  # Refresh daemons, cron jobs + notif bindings
  schoolboxcloud-restart-daemons "$INSTANCE_ID";
  schoolboxcloud-rebuild-cronjobs "$INSTANCE_ID";
  php "$site_root"/tools/notif/route/refresh-binding-cache.php -vvv
  rm -fR "$working_dir"
}

main "$@"
