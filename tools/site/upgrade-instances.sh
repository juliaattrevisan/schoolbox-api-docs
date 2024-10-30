#!/bin/bash
# Upgrades each instance to match the installed version
# - Ensures data dirs configured in DB exist on disk
# - Runs SQL updates (primary host only)
# - Restarts daemons (primary host only)
# - Rebuilds cron jobs (primary host only)
# - Rebuilds binding cache (primary host only)
# - Rebuilds module and route cache (primary host only)
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
declare single_instance_mode=0
declare site_root              # No trailing slash, eg '/usr/share/schoolbox'
declare is_primary_host        # One of [0|1]
declare instance_list          # Set of instances for this host

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
  echo "  Upgrades all instances for this host"
  echo "Usage:"
  echo "  $0 --is-primary-host [0|1] [-v|--verbose]"
  echo "Args:"
  echo "  --is-primary-host      Required. Is this a primary (1) or non-primary (0) host."
  echo "                                   (Primary host has daemons/crons + runs SQL updates)"
  echo "  --instance-id          Optional. If specified, only upgrade this instance"
  echo "  -v,--verbose           Optional. Log with increased verbosity"
  echo "  -h,--help              Optional. Print this help message and exit"
}

# Check script is running with sufficient permissions
function check_run_as_root(){
  if [[ $EUID -ne 0 ]]; then
     error "This script must be run as root"
     exit 1
  fi
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
      --instance-id)
        instance_list=$2
        single_instance_mode=1
        shift; shift;
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

  if [[ -z $is_primary_host ]]; then
    error "You must specify if this is a primary using the --is-primary-host [0|1] flag"
    exit 1
  fi
  if [[ -z $instance_list ]]; then
    if ! instance_list=$(schoolbox-read-instances --option instance-list | sort); then
      error "Unable to parse instance list from ini file: '/etc/schoolbox/instances.ini'"
      exit 1;
    fi
  fi
  for i in $instance_list; do
    if ! INSTANCE_ID=$i schoolbox-read-instances --option INSTANCE_ID; then
      error "instance id '$i' is invalid!"
      exit 1;
    fi
  done
  debug "site_root: '$site_root'"
  debug "instance_list: $(echo -e "$instance_list" | tr '\n' ' ')"
  debug "single_instance_mode: $single_instance_mode"
}

function update_schoolbox_update_error_fact_file(){
  temp_file_dir="/tmp/facter";
  fact_file_dir="/etc/facter/facts.d";
  instances_fact_file_pattern="schoolbox_*_update.json";
  master_fact_file_name="schoolbox_update.json";

  log "Updating fact file for schoolbox_update_error";
  mkdir -p /tmp/facter

  #overwrite fact file values with new values and write to temp fact file
  jq -s \
    'reduce .[] as $instance ({}; . * $instance)' \
    ${fact_file_dir}/${master_fact_file_name} ${temp_file_dir}/${instances_fact_file_pattern} \
     > "${temp_file_dir}/${master_fact_file_name}.new";

  #create an array of instance ids
  current_instance_ids=$(schoolbox-read-instances --option instance-list | jq -Rn '[inputs|select(length>0)]')

  #delete instances not present in /etc/schoolbox/instances.ini from the fact file
  jq -c \
    --arg keys_to_keep "${current_instance_ids}" \
    '.[] |= with_entries( select( .key as $k | $keys_to_keep | index($k) ) )' \
    ${temp_file_dir}/${master_fact_file_name}.new > "${fact_file_dir}/${master_fact_file_name}";

  # Remove temp files. (-f flag prevents errors when no instance present on server.)
  rm "${temp_file_dir}/${master_fact_file_name}.new"
  rm -f "${temp_file_dir}/${instances_fact_file_pattern}"
}

# Ensures data-dirs exist (all hosts) then runs SQL updates (primary hosts only)
function upgrade_instances(){

  if [[ $is_primary_host == '1' ]]; then
    # Note site_down runs an SQL UPDATE, so it only makes sense to call site_down on the primary host.
    # (databases exist on the primary host only).
    log "This is the primary host, so taking all instances offline (by enabling maintenance mode)..."
    for i in $instance_list; do
    {
        if INSTANCE_ID="$i" "$site_root"/tools/maintenance/site_down.sh -eq 0 ; then
          log "Maintenance mode enabled on instance '$i'."
        else
          log "Could not enable maintenance mode on instance '$i'.  Exiting."
          exit 1;
        fi
    }
    done
    log "All instances are offline."
  else
    log "Not enabling maintenance mode because not primary host."
  fi

  log "Starting SQL updates for all instances in parallel"
  for i in $instance_list; do
  {
    # Ensure instance's log file's directory exists
    mkdir -p /var/log/schoolbox/"$i"
    instance_log_file=/var/log/schoolbox/"$i"/upgrade.log;
    log "Upgrade starting for instance '$i' - logging to $instance_log_file";

    # TODO, consider case where:
    # 1. Non-primary upgrades from v1 to v2, and creates dirs using v1 schema
    # 2. Primary upgrades from v1 to v2, creates data dirs for v1, then update scripts create for v2
    # 3. Non-primary is now lacking v2 dirs. Need to ensure cron regularly checks configured data dirs exist on non-primaries.
    local args=(--instance "$i")
    if [[ $verbose == 1 ]]; then
      args+=(--verbose)
    fi
    "$site_root"/tools/site/create-data-dirs.sh "${args[@]}" >> "$instance_log_file" 2>&1

    if [[ $is_primary_host == '1' ]]; then
    {
      echo "Running update scripts."
      INSTANCE_ID="$i" "$site_root"/tools/update-sql.sh
      # Ensure instance has JWT keys deployed. TODO, should be run once only on install, not on upgrade
      INSTANCE_ID="$i" php "$site_root"/tools/token/generateKeyPair.php --quiet
    } >> "$instance_log_file" 2>&1
    else
      echo "Skipping SQL updates on non-primary host" >> "$instance_log_file"
    fi

    rm -f /etc/facter/facts.d/"$i"_update.txt;

    {
      echo "$(date) - cleaning cache dirs..."
      INSTANCE_ID="$i" "$site_root"/tools/clean-cache-dir.sh
      echo "$(date) - resetting data dir (!storage) ownership..."
      INSTANCE_ID="$i" "$site_root"/tools/set-config-data-dirs-perms.sh --owner www-data --group www-data --skip storage_dir
    } >> "$instance_log_file" 2>&1

    if [[ $is_primary_host == '1' ]]; then
      echo "$(date) - checking saml certificate" >> "$instance_log_file";
      sql_user=$(INSTANCE_ID="$i" schoolbox-read-instances --option SQL_user);
      sql_pass=$(INSTANCE_ID="$i" schoolbox-read-instances --option SQL_passwd);
      sql_host=$(INSTANCE_ID="$i" schoolbox-read-instances --option SQL_host);
      sql_schema=$(INSTANCE_ID="$i" schoolbox-read-instances --option SQL_db);

      if ! saml_public_cert=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -BNe "SELECT value FROM config WHERE name='saml_public_cert'"); then
        error "Unable to fetch 'saml_public_cert' from database" >> "$instance_log_file" 2>&1
      else
        saml_check=$(echo "$saml_public_cert" | sed "s/\\\\n/\n/g" | openssl x509 -noout >/dev/null 2>&1);
        debug "saml_check: '$saml_check'" >> "$instance_log_file" 2>&1
        if [ "$saml_check" != "0" ]; then
            INSTANCE_ID="$i" "$site_root"/tools/saml/createKeys.php >> "$instance_log_file" 2>&1
        fi
      fi
    fi
    log "Upgrade complete for instance '$i'";

    # Bring instance back online.
    # Note site_up runs an SQL UPDATE, so it only makes sense to call site_up on the primary host (only primary hosts have databases).
    if [[ $is_primary_host == '1' ]]; then
      if INSTANCE_ID="$i" "$site_root"/tools/maintenance/site_up.sh -eq 0 ; then
        log "Maintenance mode disabled on instance '$i'."
      else
        log "Could not disable maintenance mode on instance '$i'.  Continuing."
      fi
    else
      log "Not disabling maintenance mode because not primary host."
    fi
  } &
  done
  # Wait for all child instance upgrades to complete
  sleep 1 && log "Parent script waiting for all child upgrade instance processes to complete...";
  wait;
  log "All child upgrade instance processes appear to have completed";
}

function restart_php_fpm(){
  log "Reloading php-fpm ..."
  service "$(systemctl -a  --plain --state=active | grep -Eo 'php.+fpm')" reload         # Ensure web requests use upgraded code
}

function restart_daemons(){
  if [[ $is_primary_host == '1' ]]; then
    log "---restarting daemons on primary host---";
    for instance in $instance_list; do
      INSTANCE_ID="$instance" /usr/share/schoolbox/tools/notif/allrestart
    done
    systemctl restart schoolbox-cloud-socketd.service
  else
    log "---skipping daemon restart on secondary host---";
  fi
}

function rebuild_cron_jobs(){
  if [[ $is_primary_host == '1' ]]; then
    log "---rebuilding cron jobs on primary host---";
  else
    log "---removing cron jobs on secondary host---";
  fi
  local args=(--is-primary-host "$is_primary_host")
  if [[ $verbose == 1 ]]; then
    args+=(-v)
  fi
  if [[ $single_instance_mode == 1 ]]; then
    args+=(--instance "$instance_list")
  fi
  schoolboxcloud-rebuild-cronjobs "${args[@]}"
}

function reload_binding_cache(){
  if [[ $is_primary_host == '1' ]]; then
    log "---reloading binding cache (in parallel)---";
    for i in $instance_list; do
    {
      instance_log_file_2=/var/log/schoolbox/"$i"/upgrade.log;
      log "binding cache refresh starting for instance $i - logging to $instance_log_file_2";
      /usr/share/schoolbox/tools/notif/route/refresh-binding-cache.php --instance="$i" >> "$instance_log_file_2" 2>&1;
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

function reload_route_and_module_cache(){
  if [[ $is_primary_host == '1' ]]; then
    log "---reloading route+module cache (in parallel)---";
    for i in $instance_list; do
    {
      instance_log_file_2=/var/log/schoolbox/"$i"/upgrade.log;
      log "route+module cache refresh starting for instance $i - logging to $instance_log_file_2";
      INSTANCE_ID="$i" /usr/share/schoolbox/tools/module/refresh-cache.php >> "$instance_log_file_2" 2>&1;
      log "route+module cache refresh finished for instance $i";
    } &
    done;
    # Wait for all child processes to finish
    sleep 1 && log "Parent script waiting for all child processes to complete...";
    wait;
    log "All child processes have completed";
  else
    log "---skipping route+module cache reload on secondary host---";
  fi
}

# Main script logic
function main() {
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  check_run_as_root
  parse_args "$@"
  parse_global_vars "$@"
  reload_route_and_module_cache
  upgrade_instances
  # Ensure any routes dependent on config options run based on those configs,
  # by running after upgrade as well.
  reload_route_and_module_cache
  update_schoolbox_update_error_fact_file
  restart_php_fpm
  restart_daemons
  rebuild_cron_jobs
  reload_binding_cache
  log "Upgrade complete"
}

main "$@";
