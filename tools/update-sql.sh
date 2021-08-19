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
readonly L_ERROR=400

# Global vars
declare package                  # 'schoolbox'|'schoolboxdev'
declare site_root                # Schoolbox root dir
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
  file_path=$(readlink -f "$0");
  full_path=${file_path%/*};

  site_root=$(readlink -f "$full_path"/../);
  package=${site_root##*/};
  if [[ $package == 'vagrant' ]]; then
    package='schoolbox'
  fi

  if [ ! -f "/etc/$package/instances.ini" ]; then
    error "No ini found at '/etc/$package/instances.ini";
    error "No cache directories cleaned";
    exit;
  fi

  log "Using db credentials from: '/etc/$package/instances.ini'";

  # Collect Credentials
  sql_user=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_user);
  sql_pass=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_passwd);
  sql_host=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_host);
  sql_db=$(schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_db);
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
}

# Dump the instance's database
backup_database(){
  sql_ver=$1

  if [[ "$(echo -e "20.1.alpha1\n${sql_ver}" | sort -V | head -n 1)" != "20.1.alpha1" ]]; then
    # DB schema is < 20.1.alpha1
    backup_dir_name='file_dir'
  else
    # DB schema is >= 20.1.alpha1
    backup_dir_name='backup_dir'
  fi
  backup_dir=$(mysql -u "$sql_user" -p"$sql_pass" -h "$sql_host" "$sql_db" -e "SELECT value FROM config WHERE name='$backup_dir_name' \G;" | grep 'value' | cut -d' ' -f2);
  backup_dir=$(readlink --canonicalize-missing "$backup_dir"/../)
  if [ "$backup_dir" == "" ]; then
    error "Unable to detect backup dir"
    exit 1;
  fi

  backup_file="$backup_dir/$sql_ver-$(date +%Y-%m-%d_%H-%M-%S).sql.gz"

  # Skip backup if backup exists for same version and within last hour.
  # Need to check for min size because gzip of empty file produces non-empty file.
  existing_backup=$(find "$backup_dir" -mindepth 1 -maxdepth 1 -name "$sql_ver-$(date +%Y-%m-%d_%H-)*.sql.gz" | head -n 1);
  if [[ -f $existing_backup ]] && [[ $(wc -c "$existing_backup" | awk '{ print $1 }') -gt 10000 ]]; then
    log "Backup already completed at '$existing_backup'";
    backup_file=$existing_backup;
  else
    log "Backing up database before changes";
    mkdir -p "$backup_dir";
    if ! mysqldump --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" | gzip > "$backup_file"; then
      error "Unable to backup database '$sql_db'"
      exit 1
    fi
    # For some reason the above test is not failing when the dump fails. Need to check we actually got data.
    if [[ $(wc -c "$backup_file" | awk '{ print $1 }') -lt 1000 ]]; then
      error "Unable to backup database '$sql_db'. Dump file seems too small."
      exit 1
    fi
    log "Database backed up to '$backup_file'"
  fi
}

# Execute a single SQL/PHP update script
execute_upgrade_script(){
  script=$1
  log "Starting script: $script"
  if [[ $script == *".sql" ]]; then
    # Check for and report errors
    if ! mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" < "$script"; then
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

  # Set Site Package version in config will be blank in vagrant
  local package_version
  if dpkg -l | grep -qE "\s${package}\s"; then
    package_version=$(dpkg -s $package | grep Version | awk '{print $2}')
  fi
  log "Setting 'site_package' to '$package_version'"
  mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -e "UPDATE config SET value = '$package_version' WHERE name = 'site_package';"

  # Run update scripts in release order, SQL first then PHP for each release
  for release in $(echo "$version_list" | sed -n "$start","$end"p ); do
    log "Running update $release";

    # Run SQL update scripts for this release
    for script in $(find "${site_root}/updates/$release" -type f  | grep -E "(.+\.sql|.+\.php)$" | sort); do
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
    echo "$package""_update_error=$error_int" > /etc/facter/facts.d/"$package"_update.txt;
    echo "$package""_update_errormsg=$error_msg" >> /etc/facter/facts.d/"$package"_update.txt;
    error "SQL updates completed with errors. Please manually fix issues or rollback to pre-update backup."
  else
    echo "$package""_update_error=0" > /etc/facter/facts.d/"$package"_update.txt;
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
  "$site_root"/tools/notif/syscheck.php -v;
  sys_check_exit_code=$?
  if [ $sys_check_exit_code -ne 0 ]; then
    error "Error: all system dependencies must be available to start the update"
    exit $sys_check_exit_code;
  fi
}


# Main logic flow
main(){
  check_instance_id
  check_run_as_root
  derive_global_vars "$@"

  # Build update version list, derive latest version, collect current SQL version
  version_list=$(find "$site_root"/updates -mindepth 1 -maxdepth 1 -type d -printf %f\\n | sort -V)
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
  backup_database "$sql_ver"
  execute_upgrade_scripts "$version_list" "$start" "$end"
  update_puppet_facts
}

main "$@"
