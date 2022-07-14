#!/bin/bash
# SQL Update Script
# Description:
#       Updates a single instance's database schema and values to match the installed package version
#       For each update release, the update scripts are run in alphanumeric order
#
# @copyright   Copyright 2021, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

# Global constants
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
readonly UNSUPPORTED_FROM_MAJOR='20.1' # Require force if upgrading a schema at this version or below

# Global vars
declare sql_user
declare sql_pass
declare sql_host
declare sql_db
declare error_int=0;             # Overall script error code
declare error_msg='';            # Overall script error messages

# Consistently log messages
log(){
  $LOG $L_INFO "$1"
}
warn(){
  $LOG $L_WARN "$1"
}
error(){
  $LOG $L_ERROR "$1"
}

# Ensures we are running in the context of an instance
check_instance_id(){
  if [[ -z "$INSTANCE_ID" ]]; then
    error "Unable to derive an INSTANCE_ID"
    exit 1
  fi
  log "Starting SQL update process for instance: '$INSTANCE_ID'";
}

# Check script is running with sufficient permissions. Upgrade scripts must be run as root.
check_run_as_root(){
  if [[ $EUID -ne 0 ]]; then
     error "This script must be run as root"
     exit 1
  fi
}

# Derive global vars that will be used by multiple subsequent functions
derive_global_vars(){
  if [ ! -f "/etc/schoolbox/instances.ini" ]; then
    error "No ini found at '/etc/schoolbox/instances.ini";
    error "No cache directories cleaned";
    exit;
  fi

  log "Using db credentials from: '/etc/schoolbox/instances.ini'";

  # Collect Credentials
  sql_user=$(schoolbox-read-instances --option SQL_user);
  sql_pass=$(schoolbox-read-instances --option SQL_passwd);
  sql_host=$(schoolbox-read-instances --option SQL_host);
  sql_db=$(schoolbox-read-instances --option SQL_db);
}

# Check we could detect current and desired SQL versions
check_sql_versions(){
  new_ver=$1
  sql_ver=$2

  log "Checking for SQL updates";
  log "Current SQL Version: $sql_ver";
  log "New SQL Version: $new_ver";

  if [ -z "$sql_ver" ]; then
    error "Unable to collect current database version. Please check your database credentials.";
    exit 1;
  fi

  if [ "$sql_ver" == "$new_ver" ]; then
    log "No updates required";
    exit;
  fi

  # Ensure all required update dirs are present in this package. Never allow this.
  if [[ ! -e /usr/share/schoolbox/updates/"$sql_ver" ]]; then

    # There are two scenarios where this may occur.
    if [[ $(echo -e "$new_ver\n$sql_ver" | sort -V | tail -n 1 ) != "$new_ver" ]]; then
      # Case 1. PRD-to-STG sync but PRD is in front of STG or package has been rolled back
      error "Current SQL version is in front of Package SQL version."
      error "This is typically due to:"
      error "  1. PRD-to-STG sync where STG package is behind PRD package, or"
      error "  2. Package has been rolled back and SQL is behind SQL version"
      error "If this is a PRD-to-STG sync, you must:"
      error "  a. Restore from a local STG backup"
      error "  b. Upgrade the STG site"
      error "  c. Re-execute the PRD-to-STG sync"
      error "If you have rolled back the package, you must either:"
      error "  a. Upgrade the package version, or"
      error "  b. Restore from an earlier SQL backup file"
    else
      # Case 2. SQL is so old that package no longer supports upgrade from it
      error "Current SQL version update dir not found: '/usr/share/schoolbox/updates/$sql_ver'"
      error "Cannot guarantee all required intervening update dirs are present!"
      error "You are probably trying to upgrade a dataset that is too old for this code version."
      error " * Upgrades:        You must only upgrade a single major version at a time."
      error " * Prd-to-Stg sync: The PRD version is too old, and no longer supported."
      error " * restore.sh:      Select a newer backup, or deploy older package."
      error " * Vagrant:         Checkout older codebase, provision, save dataset, checkout desired version, re-provision"
    fi
    exit 1
  fi

  # Ensure we are not upgrading from a major version where we know there are issues. Allow force override
  local sql_major
  sql_major=$(echo "$sql_ver" | grep -Eo '[0-9]+\.[0-9]+')

  if [[ $sql_major > $UNSUPPORTED_FROM_MAJOR ]]; then
    log "Upgrades supported from major version: '$sql_major'"
  else
    if [[ $SB_FORCE_UPGRADE == 1 ]]; then
      warn "Forcing upgrade from unsafe major version '$sql_major'"
    else
      error "Refusing upgrade from unsafe major version '$sql_major' as it is <= '$UNSUPPORTED_FROM_MAJOR'"
      error "Set environment var SB_FORCE_UPGRADE to 1 to force unsafe upgrade"
      # Check if this is being invoked by vagrant provision (vagrant-shell > refresh.sh > update-sql.sh)
      if grep -qE '^vagrant-shell' /proc/"$(ps -o ppid= $PPID | xargs )"/comm ; then
        error "In vagrant, set 'force_upgrade' to 'true' in your vagrant.yaml to set this env var"
      fi
      exit 1
    fi
  fi


}

# Execute a single SQL/PHP update script
execute_upgrade_script(){
  script=$1
  log "Starting script: $script"
  if [[ $script == *".sql" ]]; then
    # Check for and report errors
    if ! (echo 'SET MAX_STATEMENT_TIME=0; '; cat "$script") | mysql  -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db"; then
      error "Error running update: $script"
      error_int=1;
      error_msg="$error_msg,$script";
    else
      log "Success running update: $script";
    fi
  elif [[ $script == *".php" ]]; then
    current_dir=$(pwd)
    script_path=${script%/*};
    cd "$script_path" || exit 1;
    php_script=${script##*/};
    # Check for and report errors
    if ! php -f "$php_script" 2>&1; then
      error "Error running update: $script"
      error_int=1;
      error_msg="$error_msg,$script";
    else
      log "Success running update: $script"
    fi
    cd "$current_dir" || exit 1;
  fi
}

# Execute all relevant releases, incrementing the instance's `site_version` after each release
execute_upgrade_scripts(){
  version_list=$1
  start=$2
  end=$3

  log "Updating database to new version";
  # Run update scripts in release order, SQL first then PHP for each release
  for release in $(echo "$version_list" | sed -n "$start","$end"p ); do
    log "Running update $release";

    # Run SQL update scripts for this release
    for script in $(find "/usr/share/schoolbox/updates/$release" -type f  | grep -E "(.+\.sql|.+\.php)$" | sort); do
      execute_upgrade_script "$script"
    done

    # Update the config table to show current release version
    mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -e "UPDATE \`config\` SET value='$release' WHERE name='site_version';";
  done
}


# Reset facter fact
update_puppet_facts(){
  mkdir -p /etc/facter/facts.d/ >/dev/null 2>&1;
  if [ $error_int -ne 0 ]; then
    error_msg=$(echo "$error_msg" | cut -d',' -f2-100);
    echo "schoolbox_update_error=$error_int" > /etc/facter/facts.d/schoolbox_update.txt;
    echo "schoolbox_update_errormsg=$error_msg" >> /etc/facter/facts.d/schoolbox_update.txt;
    error "SQL updates completed with errors. Please manually fix issues or rollback to pre-update backup."
  else
    echo "schoolbox_update_error=0" > /etc/facter/facts.d/schoolbox_update.txt;
    log "SQL updates completed";
  fi
}


# Validates the 'start' + 'end' update dirs
check_start_end_dirs(){
  start=$1
  end=$2
  if [ -z "$end" ]; then
    error "Error: Unable to detect final SQL update to apply, no changes made"
    exit;
  fi
  if [ "$start" -gt "$end" ]; then
    error "Error: SQL version greater than update, no changes made"
    exit;
  fi
}


# Ensure all required resources are available before running scripts, hard fail if not
check_system_dependencies(){
  /usr/share/schoolbox/tools/notif/syscheck.php -v;
  sys_check_exit_code=$?
  if [ $sys_check_exit_code -ne 0 ]; then
    error "Error: all system dependencies must be available to start the update"
    exit $sys_check_exit_code;
  fi
}

set_site_package(){
  # Set Site Package version in config will be blank in vagrant
  local package_version
  if dpkg -l | grep -qE "\sschoolbox\s"; then
    package_version=$(dpkg -s schoolbox | grep Version | awk '{print $2}')
  fi
  log "Setting 'site_package' to '$package_version'"
  mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -e "UPDATE config SET value = '$package_version' WHERE name = 'site_package';"
}


# Main logic flow
main(){
  check_instance_id
  check_run_as_root
  derive_global_vars "$@"

  # Always execute this, regardless of whether SQL upgrades required, as we may be deploying a dataset that doesn't require SQL updates
  set_site_package

  # Build update version list, derive latest version, collect current SQL version
  version_list=$(find /usr/share/schoolbox/updates -mindepth 1 -maxdepth 1 -type d -printf %f\\n | sort -V)
  new_ver=$(echo "$version_list" | tail -n 1)
  sql_ver=$(mysql -u "$sql_user" -p"$sql_pass" -h "$sql_host" "$sql_db" -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2);
  check_sql_versions "$new_ver" "$sql_ver"

  # Determine first release to apply
  for release in ${version_list}; do
    if [[ $sql_ver != "$release" ]] && [[ $(echo -e "$sql_ver\n$release" | sort -V | head -n1) == "$sql_ver" ]]; then
      first_update="${release}";
      break;
    fi;
  done
  if [ -z "$first_update" ]; then
    error "Error: Unable to determine first update dir to execute"
    exit 1;
  fi
  start=$(echo "$version_list" | grep -n "^${first_update}$" | cut -d':' -f1);
  end=$(echo "$version_list" | grep -n "^${new_ver}$" | cut -d':' -f1);

  check_start_end_dirs "$start" "$end"
  check_system_dependencies
  execute_upgrade_scripts "$version_list" "$start" "$end"
  update_puppet_facts
}

main "$@"
