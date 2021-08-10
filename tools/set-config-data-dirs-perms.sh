#!/bin/bash
# Sets ownership of data dirs, identified by their config name
# Fetches the path of the directory from the an instance's database
# If not data dir args are provided, then all dirs are updated
# Usage:
#   set-config-data-dirs-perms.sh --owner www-data                  --dir twig_cache_dir --dir purifier_cache_dir
#   set-config-data-dirs-perms.sh --owner www-data --group www-data --skip storage_dir
#
# @copyright   Copyright 2020, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

# Global const
readonly LOG=/usr/bin/alaress-logger
readonly L_DEBUG=100
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
readonly ARG_OWNER='owner'
readonly ARG_GROUP='group'
readonly ARG_DIR='dir'
readonly ARG_SKIP_DIR='skip'
readonly ARG_VERBOSE_SHORT='v'
readonly ARG_VERBOSE_LONG='verbose'
readonly MODE_DIR=775
readonly MODE_DIR_FILES=664

# Global vars and defaults
declare site_root;
declare owner;                   # CLI arg. Optional. If provided, change owner to this
declare group;                   # CLI arg. Optional. If provided, change group to this
declare verbose='0'
declare available_data_dirs=();  # Fetched from Database
declare data_dir_include=();     # CLI args
declare data_dir_skip=();        # CLI args
declare data_dirs=();            # Data dirs we will actually modify

function debug(){
  if [[ $verbose == '1' ]]; then
    $LOG $L_DEBUG "$1"
  fi
}

function log(){
  $LOG $L_INFO "$1"
}

function warn(){
  $LOG $L_WARN "$1"
}

function error(){
  $LOG $L_ERROR "$1"
}


# Check script is running with sufficient permissions
function check_run_as_root(){
  if [[ $EUID -ne 0 ]]; then
     error "This script must be run as root"
     exit 1
  fi
}

# Set PATH when invoked by cron
function set_up_env(){
  export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games';
}


# Parse file system paths
function parse_paths(){
  local this_file_path;
  local this_file_dir;
  this_file_path=$(readlink -f "$0");
  this_file_dir=${this_file_path%/*};
  site_root=$(readlink -f "$this_file_dir"/../);
}


# Parse CLI args
function parse_cli_args(){
  while [[ $# -gt 0 ]]; do
    case $1 in
      -$ARG_VERBOSE_SHORT|--$ARG_VERBOSE_LONG)
      verbose="1"; shift;
      ;;
      --$ARG_OWNER)
      owner="$2"; shift; shift;
      ;;
      --$ARG_GROUP)
      group="$2"; shift; shift;
      ;;
      --$ARG_DIR)
      data_dir_include+=("$2"); shift; shift;
      ;;
      --$ARG_SKIP_DIR)
      data_dir_skip+=("$2")
      shift # past argument
      shift # past value
      ;;
      *)    # unknown option
      error "Unrecognised option: '$1'"
      errors='1'
      shift # past argument
      ;;
    esac
  done

  if [[ $errors == '1' ]]; then
    exit 1
  fi
}


# Parse SQL credentials for an instance
function parse_sql_credentials(){
  local conf_file=$site_root/www/conf/conf.php;
  local instance_id;

  instance_id=$(schoolbox-conf --conf="$conf_file" --option=INSTANCE_ID);
  if [ -z "$instance_id" ]; then
    error "Unable to derive an INSTANCE_ID"
    exit 1;
  fi
  sql_user=$(schoolbox-conf --conf="$conf_file" --option=SQL_user);
  if [ -z "$sql_user" ]; then
    error "Unable to derive an SQL_user"
    errors='1'
  fi
  sql_pass=$(schoolbox-conf --conf="$conf_file" --option=SQL_passwd);
  if [ -z "$sql_pass" ]; then
    error "Unable to derive an SQL_passwd"
    errors='1'
  fi
  sql_host=$(schoolbox-conf --conf="$conf_file" --option=SQL_host);
  if [ -z "$sql_host" ]; then
    error "Unable to derive an SQL_host"
    errors='1'
  fi
  sql_schema=$(schoolbox-conf --conf="$conf_file" --option=SQL_db);
  if [ -z "$sql_schema" ]; then
    error "Unable to derive an SQL_db"
    errors='1'
  fi
  if [[ $errors == '1' ]]; then
    exit 1
  fi
}


# Parse available data dirs from single source of truth
function parse_available_data_dirs(){
  local data_dirs_config;

  data_dirs_config=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -ss -e "SELECT value from config WHERE name = 'data_dirs_defaults'")
  debug "Data dir defaults: '$data_dirs_config'"
  IFS=';' read -r -a array <<< "$data_dirs_config"
  for element in "${array[@]}"; do
    IFS=':' read -r -a sub_array <<< "$element"
    available_data_dirs+=("${sub_array[0]}")
    debug "${sub_array[0]}"
  done
}


function derive_data_dirs() {

  # Check the include dir is valid
  for dir_include in ${data_dir_include[*]}; do
    local valid='0'
    for dir_available in ${available_data_dirs[*]}; do
      if [ "$dir_include" == "$dir_available" ]; then
        valid='1'
        break
      fi
    done
    if [ $valid == '0' ]; then
      error "Invalid arg value '--$ARG_DIR $dir_include"
      errors='1'
    fi
  done

  # Check the skip dir is valid
  for dir_skip in ${data_dir_skip[*]}; do
    local valid='0'
    for dir_available in ${available_data_dirs[*]}; do
      if [ "$dir_skip" == "$dir_available" ]; then
        valid='1'
        break
      fi
    done
    if [ $valid == '0' ]; then
      error "Invalid arg value '--$ARG_SKIP_DIR $dir_skip"
      errors='1'
    fi
  done

  # Check not specified to include and skip simultaneously
  for dir_include in ${data_dir_include[*]}; do
    for dir_skip in ${data_dir_skip[*]}; do
      if [ "$dir_include" == "$dir_skip" ]; then
        error "You cannot specify to include and skip the same data dir: '$dir_include'"
        errors='1'
      fi
    done
  done

  if [[ $errors == '1' ]]; then
    exit 1
  fi

  # If nothing explicitly included, include all
  if [ ${#data_dir_include[@]} -eq 0 ]; then
    data_dir_include=("${available_data_dirs[@]}")
  fi

  # Add these, skipping any that were explicitly requested to skip
  for dir_available in ${data_dir_include[*]}; do
    local include='1'
    for dir_skip in ${data_dir_skip[*]}; do
      if [ "$dir_available" == "$dir_skip" ]; then
        include='0'
        break;
      fi
    done
    if [ $include == '1' ]; then
      data_dirs+=("$dir_available")
    fi
  done
}


# Updates directory ownership and permissions
function update_directories(){
  for data_dir in ${data_dirs[*]}; do
    local dir_path;
    if ! dir_path=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -ss -e "SELECT value from config WHERE name = '$data_dir'"); then
      error "Unable to execute mysql command to fetch path for data dir '$data_dir'"
    elif [[ -z $dir_path ]]; then
      warn "No path set for data dir '$data_dir'. Either config name does not exist, or config value is empty. Skipping."
    else
      # Change owner, group, or both
      log "Updating directory ($data_dir): $dir_path"
      if [[ -n $owner ]] && [[ -n $group ]]; then
        debug "Setting file owner and group to $owner:$group"
        chown -R "$owner":"$group" "$dir_path"
      elif [[ -n $owner ]] && [[ -z $group ]]; then
        debug "Setting file owner to $owner"
        chown -R "$owner" "$dir_path"
      elif [[ -z $owner ]] && [[ -n $group ]]; then
        debug "Setting file group to $group"
        chgrp -R "$group" "$dir_path"
      else
        debug "Retaining current owner and group"
      fi
      # TODO Should be 755/644, but need this for www-data to write vagrant-owned dirs/files in dev environment. See IST-1088
      debug "Setting directory modes to $MODE_DIR"
      find "$dir_path" -type d -exec chmod -c $MODE_DIR {} +
      debug "Setting file modes to $MODE_DIR_FILES"
      find "$dir_path" -type f -exec chmod -c $MODE_DIR_FILES {} +
    fi
  done
}


function main(){
  log "Updating data directory permissions";
  check_run_as_root
  set_up_env
  parse_paths
  parse_cli_args "$@"
  parse_sql_credentials
  parse_available_data_dirs
  derive_data_dirs
  update_directories
  log "Data directory permissions updated"
}

main "$@"

