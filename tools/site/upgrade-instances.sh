#!/bin/bash
# Upgrades each instance to match the installed version
# - Ensures data dirs configured in DB exist on disk
# - Runs SQL updates (primary host only)
# - Restarts daemons (primary host only)
# - Rebuilds cron jobs (primary host only)
# - Rebuilds binding cache (primary host only)
#
# @copyright   Copyright 2021, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

# Global constants
readonly LOG=/usr/bin/alaress-logger
readonly L_DEBUG=100
readonly L_INFO=200
readonly L_ERROR=400

# Global vars
declare verbose=0
declare package           # One of [schoolbox|schoolboxdev]
declare site_root         # No trailing slash, eg '/vagrant'
declare is_primary_host   # One of [0|1]
declare instance_list     # Set of instances for this package

# Logging helpers
function debug(){
  if [[ $verbose == '1' ]]; then
    $LOG $L_DEBUG "$1"
  fi
}
function log(){
  $LOG $L_INFO "$1"
}
function error(){
  $LOG $L_ERROR "$1"
}

# Renders the help message
function help(){
  echo "Description"
  echo "  Upgrades all instances for this package (not host)"
  echo "Usage:"
  echo "  $0 --is-primary-host [0|1] [-v|--verbose]"
  echo "Args:"
  echo "  --is-primary-host  Required. Is this a primary (1) or non-primary (0) host."
  echo "                               (Primary host has daemons/crons + runs SQL updates)"
  echo "  -v,--verbose       Optional. Log with increased verbosity"
  echo "  -h,--help          Optional. Print this help message and exit"
}


# Parse CLI args
function parse_args() {
  local errors=0
  while [[ $# -gt 0 ]]; do
    case $1 in
      -v|--verbose)
        verbose=1
        shift
        ;;
      -h|--help)
        help
        shift
        exit
        ;;
      --is-primary-host)
        if [[ $2 =~ ^[01]{1}$ ]]; then
          is_primary_host=$2
          shift; shift;
        else
          error "Unrecognised value for --is-primary-host '$2'"
          errors=1
          shift;
        fi
        ;;
      *)
        error "Unrecognised option '$1'"
        errors=1
        shift
        ;;
    esac
  done
  if [[ $errors == 1 ]]; then
    help
    exit 1
  fi
}


# Derive global vars used across multiple functions
function parse_global_vars(){
  local this_file;
  local this_dir;
  this_file=$(readlink -f "$0");
  this_dir=${this_file%/*};
  site_root=$(readlink -f "$this_dir"/../../)
  if [[ ${site_root##*/} == 'schoolboxdev' ]]; then
    package='schoolboxdev'
  else
    package='schoolbox'
  fi
  if [[ -z $is_primary_host ]]; then
    error "You must specify if this is a primary using the --is-primary-host [0|1] flag"
    exit 1
  fi
  if ! instance_list=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option instance-list | sort); then
    error "Unable to parse instance list from ini file: '/etc/$package/instances.ini'"
    exit 1;
  fi
  debug "site_root: '$site_root'"
  debug "package: '$package'"
  debug "instance_list: $(echo -e "$instance_list" | tr '\n' ' ')"
}


# Ensures all data dirs defined by instances exist on disk
function create_data_dirs(){
  if [[ $verbose == 1 ]]; then
    "$site_root"/tools/site/create-data-dirs.sh --verbose
  else
    "$site_root"/tools/site/create-data-dirs.sh
  fi
}

# Ensures data-dirs exist (all hosts) then runs SQL updates (primary hosts only)
function upgrade_instances(){

  log "Starting SQL updates for all instances in parallel"
  for i in $instance_list; do
  {
    instance_log_file=/var/lib/"$package"/"$i"_upgrade.log;
    log "upgrade starting for instance $i - logging to $instance_log_file";

    # TODO, consider case where:
    # 1. Non-primary upgrades from v1 to v2, and creates dirs using v1 schema
    # 2. Primary upgrades from v1 to v2, creates data dirs for v1, then update scripts create for v2
    # 3. Non-primary is now lacking v2 dirs. Need to ensure cron regularly checks configured data dirs exist on non-primaries.
    if [[ $verbose == 1 ]]; then
      "$site_root"/tools/site/create-data-dirs.sh --instance "$i" --verbose >> "$instance_log_file" 2>&1
    else
      "$site_root"/tools/site/create-data-dirs.sh --instance "$i" >> "$instance_log_file" 2>&1
    fi

    if [[ $is_primary_host == '1' ]]; then
    {
      echo "running update"
      INSTANCE_ID="$i" "$site_root"/tools/update-sql.sh
      # Ensure instance has JWT keys deployed. TODO, should be run once only on install, not on upgrade
      INSTANCE_ID="$i" php "$site_root"/tools/token/generateKeyPair.php --quiet
    } >> "$instance_log_file" 2>&1
    else
      echo "Skipping SQL updates on non-primary host" >> "$instance_log_file"
    fi

    rm -f /etc/facter/facts.d/"$i"_update.txt;

    if [[ $is_primary_host == '1' ]]; then
    {
      echo "set default (no puppet) values"
      if [[ $package == 'schoolboxdev' ]]; then
        INSTANCE_ID="$i" "$site_root"/tools/module.sh set 'debug_show_stack_trace' '1'
        INSTANCE_ID="$i" "$site_root"/tools/module.sh set 'site_type' 'dev'
      else
        INSTANCE_ID="$i" "$site_root"/tools/module.sh set 'debug_show_stack_trace' '0'
      fi
    } >> "$instance_log_file" 2>&1
    fi

    {
      echo "$(date) - cleaning cache dirs..."
      INSTANCE_ID="$i" "$site_root"/tools/clean-cache-dir.sh
      echo "$(date) - resetting data dir (!storage) ownership..."
      INSTANCE_ID="$i" "$site_root"/tools/set-config-data-dirs-perms.sh --owner www-data --group www-data --skip storage_dir
    } >> "$instance_log_file" 2>&1

    if [[ $is_primary_host == '1' ]]; then
      echo "$(date) - checking saml certificate" >> "$instance_log_file";
      sql_user=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_user);
      sql_pass=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_passwd);
      sql_host=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_host);
      sql_schema=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_db);

      saml_check=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -BNe "SELECT value FROM config WHERE name='saml_public_cert'" 2>/dev/null | sed "s/\\\\n/\n/g" | openssl x509 -noout >/dev/null 2>&1);
      if [ "$saml_check" != "0" ]; then
          INSTANCE_ID="$i" "$site_root"/tools/saml/createKeys.php >> "$instance_log_file" 2>&1
      fi
    fi
    log "upgrade complete for instance $i";
  } &
  done
  # Wait for all child instance upgrades to complete
  sleep 1 && log "Parent script waiting for all child upgrade instance processes to complete...";
  wait;
  log "All child upgrade instance processes appear to have completed";
}

function remove_maintenance_page(){
  # TODO, this will have no effect on cloud
  "$site_root"/tools/maintenance/site_up.sh
}

function restart_daemons(){
  if [[ $is_primary_host == '1' ]]; then
    log "---restarting daemon on primary host---";
    ${package}cloud-restart-daemons;
  else
    log "---skipping daemon restart on secondary host---";
  fi
}

function rebuild_cron_jobs(){
  if [[ $is_primary_host == '1' ]]; then
    log "---rebuilding cron jobs on primary host---";
    schoolboxcloud-rebuild-cronjobs --site-root "$site_root"
  else
    log "---skipping cron job rebuild on secondary host---";
  fi
}

function reload_binding_cache(){
  if [[ $is_primary_host == '1' ]]; then
    log "---reloading binding cache (in parallel)---";
    for i in $instance_list; do
    {
      instance_log_file_2=/var/lib/$package/"$i"_upgrade.log;
      log "binding cache refresh starting for instance $i - logging to $instance_log_file_2";
      ${package}cloud-reload-binding-cache "$i" >> "$instance_log_file_2" 2>&1;
      log "binding cache refresh finished for instance $i";
    } &
    done;
    # Wait for all child processes to finish
    sleep 1 && log "Parent script waiting for all child processes to complete...";
    wait;
    log "All child processes have completed";
  else
    log "---skipping binding cache reload on secondary host---";
  fi
}

# Main script logic
function main() {
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  parse_global_vars "$@"
  upgrade_instances
  remove_maintenance_page
  restart_daemons
  rebuild_cron_jobs
  reload_binding_cache
  log "Upgrade complete"
}

main "$@";
