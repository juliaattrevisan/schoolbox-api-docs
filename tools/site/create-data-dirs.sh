#!/bin/bash
# Ensures all data dirs in instances for this package exist on the host's filesystem
# Optionally can run for just specified instances, otherwise run for all in package
# Usage:
#   create-data-dirs.sh  [--instance [INSTANCE_ID]] [--instance [INSTANCE_ID]] [-v|--verbose] [-h|--help]
#
# @copyright   Copyright 2021, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

# Global const
readonly LOG=/usr/bin/alaress-logger
readonly L_DEBUG=100
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
readonly ARG_INSTANCE_ID='instance'
readonly ARG_VERBOSE_SHORT='v'
readonly ARG_VERBOSE_LONG='verbose'
readonly ARG_HELP_SHORT='h'
readonly ARG_HELP_LONG='help'
# This is a list of data dir config names over a range of SB versions (so we can support upgrade from older SQL version)
readonly FALLBACK_DATA_DIR_CONFIG_NAMES=(purifier_cache_dir twig_cache_dir backup_dir file_dir myfile_dir submission_dir returns_dir dropbox_dir zip_dir thumbnail_dir xml_dir portrait_dir stream_dir myfiles_cache_dir weather_dir storage_dir minify_dir logs_dir video_dir submission_upload_tmp_dir)

# Global vars and defaults
declare ini_file;                # eg '/etc/schoolbox[dev]/instances.ini'
declare instance_list            # Instances found in instances.ini
declare -A instances=();         # Optional. If present just run for the specified instance, otherwise run for all instances.
declare available_data_dirs;     # Fetched from Database. Reset when iterating each instance.
declare -A data_dir_map;         # Map of data_dir config.name to config. Reset when iterating each instance.
declare global_errors=0          # Were any errors encountered during script
declare fuzzy_fallback           # If using an pre 20.1.7 SQL version, we won't have the new 'data_dirs_defaults' config available.
                                 # In this case, we'll fall back to this list. Reset when iterating each instance.

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

# Renders the help message
function help(){
  echo "Description"
  echo "  Ensures each data dir in each instance's config exists on disk"
  echo "Usage:"
  echo "  $0 [--$ARG_INSTANCE_ID INSTANCE_ID] [-$ARG_VERBOSE_SHORT|--$ARG_VERBOSE_LONG] [-$ARG_HELP_SHORT|--$ARG_HELP_LONG]"
  echo "Args:"
  echo "  --$ARG_INSTANCE_ID     Optional. If declared, check just this instance. Else check all instances"
  echo "  -$ARG_VERBOSE_SHORT,--$ARG_VERBOSE_LONG   Optional. Log with increased verbosity"
  echo "  -$ARG_HELP_SHORT,--$ARG_HELP_LONG      Optional. Print this help message and exit"
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


# Parse CLI args
function parse_cli_args(){
  while [[ $# -gt 0 ]]; do
    case $1 in
      -"$ARG_VERBOSE_SHORT"|--"$ARG_VERBOSE_LONG")
        verbose="1"; shift;
        ;;
      -"$ARG_HELP_SHORT"|--"$ARG_HELP_LONG")
        help; exit
        ;;
      --"$ARG_INSTANCE_ID")
        if [[ $# -lt 2 ]]; then
          error "No value found for arg '--$ARG_INSTANCE_ID'"
          exit 1;
        fi
        instances["$2"]=1; shift; shift;
        ;;
      *)
        error "Unrecognised option: '$1'"
        errors='1'
        shift
      ;;
    esac
  done
  if [[ $errors == '1' ]]; then
    help
    exit 1
  fi
}


# Check any specified instances are recognised (also loads available instances)
function validate_instances(){
  local errors=0

  for instance_id in "${!instances[@]}"; do
    local found=0
    for available_instance_id in $instance_list; do
      if [[ $instance_id == "$available_instance_id" ]]; then
        found=1
        break;
      fi
    done
    if [[ $found == 0 ]]; then
      error "Unrecognised instance: '$instance_id'"
      errors=1
    fi
  done

  if [[ $errors == '1' ]]; then
    exit 1
  fi
}

# Derive global vars
function parse_global_vars() {

  ini_file="/etc/schoolbox/instances.ini"
  if [[ ! -f $ini_file ]]; then
    error "No instances.ini file found at '$ini_file'"
    exit 1
  fi
  if ! instance_list=$(schoolbox-read-instances --option instance-list | sort); then
    error "Unable to parse instance list from ini file: '$ini_file'"
    exit 1;
  fi
  debug "instance_list: $(echo -e "$instance_list" | tr '\n' ' ')"
}


# Parse SQL credentials for an instance
function parse_sql_credentials(){
  local instance_id=$1;

  sql_user=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --option SQL_user);
  if [ -z "$sql_user" ]; then
    error "Unable to derive an SQL_user"
    errors='1'
  fi
  sql_pass=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --option SQL_passwd);
  if [ -z "$sql_pass" ]; then
    error "Unable to derive an SQL_passwd"
    errors='1'
  fi
  sql_host=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --option SQL_host);
  if [ -z "$sql_host" ]; then
    error "Unable to derive an SQL_host"
    errors='1'
  fi
  sql_schema=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --option SQL_db);
  if [ -z "$sql_schema" ]; then
    error "Unable to derive an SQL_db"
    errors='1'
  fi
  if [[ $errors == '1' ]]; then
    exit 1
  fi
}


# Parse available data dirs from single source of truth
function parse_available_data_dirs_config_names(){
  local data_dirs_config;
  fuzzy_fallback=0;       # Reset for current instance
  available_data_dirs=(); # Reset for current instance

  data_dirs_config=$(mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -ss -e "SELECT value from config WHERE name = 'data_dirs_defaults'")
  debug "Data dir defaults: '$data_dirs_config'"
  if [[ -z $data_dirs_config ]]; then
    local sql_version;
    sql_version=$(mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -ss -e "SELECT value from config WHERE name = 'site_version'")
    local message="Data dir default config not found or empty. SQL version: $sql_version"
    if [[ "$(echo -e "20.1.7\n${sql_version}" | sort -V | head -n 1)" == "20.1.7" ]]; then
      error "$message"
    else
      warn "$message. Falling back to fuzzy list"
      available_data_dirs=("${FALLBACK_DATA_DIR_CONFIG_NAMES[@]}")
      fuzzy_fallback=1
    fi
  else
    IFS=';' read -r -a array <<< "$data_dirs_config"
    for element in "${array[@]}"; do
      IFS=':' read -r -a sub_array <<< "$element"
      available_data_dirs+=("${sub_array[0]}")
      debug "${sub_array[0]}"
    done
  fi
}


function create_data_dirs() {
  local data_dir_value;
  data_dir_map=(); # Reset for current instance

  # Check the include dir is valid
  for data_dir_name in ${available_data_dirs[*]}; do
    data_dir_value=$(mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -ss -e "SELECT value from config WHERE name = '$data_dir_name'")
    data_dir_map[$data_dir_name]="$data_dir_value"
  done

  for config_name in "${!data_dir_map[@]}"; do
    if [[ -z "${data_dir_map[$config_name]}" ]]; then
      # Don't treat as hard-error if we're using the fuzzy fallback data dir list
      if [[ $fuzzy_fallback == 0 ]]; then
        error "Config value for '$config_name' is empty"
        global_errors=1
      else
        warn "Config value for '$config_name' is empty"
      fi
    elif [[ -d ${data_dir_map[$config_name]} ]]; then
      debug "Dir '${data_dir_map[$config_name]}' already exists"
    else
      mkdir -pv "${data_dir_map[$config_name]}"
      chown -v www-data:www-data "${data_dir_map[$config_name]}"
      log "Dir '${data_dir_map[$config_name]}' created"
    fi
  done
}


function main(){
  export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games';
  check_run_as_root
  parse_cli_args "$@"
  parse_global_vars "$@"
  validate_instances

  for instance_id in $instance_list; do
    # if running for all instances or this is one of the specified instances
    if [[ ${#instances[@]} == 0 ]] || [[ -n "${instances[$instance_id]}" ]]; then
      debug "Processing instance: '$instance_id'"
      parse_sql_credentials "$instance_id"
      parse_available_data_dirs_config_names
      create_data_dirs
    fi
  done

  if [[ $global_errors == 1 ]]; then
    exit 1;
  fi
}

main "$@"

