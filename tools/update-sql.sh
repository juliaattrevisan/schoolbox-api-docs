#!/bin/bash
# SQL Update Script
# Description:
#       Updates the database schema and values to match the installed version
#       Completes all the updates between the current and new versions
#       For each update release, the update scripts are run in alphanumeric order
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#
#

file_path=$(readlink -f "$0");
full_path=${file_path%/*};

conf_file=$(readlink -f "$full_path"/../www/conf/conf.php);

site_root=$(readlink -f "$full_path"/../);
package=${site_root##*/};

current_dir=$(pwd);

if [[ -z "$INSTANCE_ID" ]]; then
  echo "Unable to derive an INSTANCE_ID" >&2
  exit 1
fi

echo "Starting SQL update process for instance: '$INSTANCE_ID'";

# Build update version list
version_list=$(find "$site_root"/updates -mindepth 1 -maxdepth 1 -type d -printf %f\\n | sort -V)
new_ver=$(echo "$version_list" | tail -n 1)

if [ ! -f "$conf_file" ]; then
  echo "New installation or conf file missing";
  echo "No cache directories cleaned";
  exit;
fi

echo "Using db credentials from: $(readlink -f "$conf_file")";

# Collect Credentials
sql_user=$(schoolbox-conf --conf="$conf_file" --option=SQL_user);
sql_pass=$(schoolbox-conf --conf="$conf_file" --option=SQL_passwd);
sql_host=$(schoolbox-conf --conf="$conf_file" --option=SQL_host);
sql_db=$(schoolbox-conf --conf="$conf_file" --option=SQL_db);

echo "Checking for SQL updates";

# Collect current SQL version
sql_ver=$(mysql -u "$sql_user" -p"$sql_pass" -h "$sql_host" "$sql_db" -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2);

if [ -z "$sql_ver" ]; then
  echo "Unable to collect current database version. Please check your database credentials.";
  exit;
fi

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
  echo "Unable to detect backup dir"
  exit 1;
fi

backup_file="$backup_dir/$sql_ver-$(date +%Y-%m-%d_%H-%M-%S).sql.gz"

echo "Current SQL Version: $sql_ver";
echo "New SQL Version: $new_ver";

if [ "$sql_ver" == "$new_ver" ]; then
  echo "No updates required";
  exit;
fi

# Compare two updates using version sort
# Returns:
#   0 if $1 = $2
#   1 if $1 < $2
#   2 if $1 > $2
versionCompare () {
  # Validate we got 2 function args
  if [[ $# != 2 ]]; then
    echo "Invalid arg count. Required: 2. Found: $#";
    exit 1;
  fi

  # Are args equal?
  if [[ $1 == "$2" ]]; then
    return 0
  fi

  # Which arg is first according to version sort?
  if [[ $(echo -e "$1\n$2" | sort -V | head -n1) == "$1" ]]; then
    return 1;
  else
    return 2;
  fi;
}

# Determine the first update directory to execute
firstUpdate='';
for release in ${version_list}; do
  versionCompare "${sql_ver}" "${release}";
  if [[ $? == 1 ]]; then
    firstUpdate=${release};
    break;
  fi;
done

start=$(echo "$version_list" | grep -n "^${firstUpdate}$" | cut -d':' -f1);
if [ -z "$start" ]; then
  echo "Error: SQL version not supported for update, no changes made";
  exit;
fi

end=$(echo "$version_list" | grep -n "^${new_ver}$" | cut -d':' -f1);
if [ -z "$end" ]; then
  echo "Error: SQL version greater than update, no changes made";
  exit;
fi

if [ "$start" -gt "$end" ]; then
  echo "Error: SQL version greater than update, no changes made";
  exit;
fi

# Ensure all required resources are available before update, hard fail if not
"$site_root"/tools/notif/syscheck.php -v;
sys_check_exit_code=$?
if [ $sys_check_exit_code -ne 0 ]; then
  echo "Error: all system dependencies must be available to start the update"
  exit $sys_check_exit_code;
fi

# Skip backup if backup exists for same version and within last hour.
# Need to check for min size because gzip of empty file produces non-empty file.
existing_backup=$(find "$backup_dir" -mindepth 1 -maxdepth 1 -name "$sql_ver-$(date +%Y-%m-%d_%H-)*.sql.gz" | head -n 1);
if [[ -f $existing_backup ]] && [[ $(wc -c "$existing_backup" | awk '{ print $1 }') -gt 10000 ]]; then
  echo "Backup already completed at '$existing_backup'";
  backup_file=$existing_backup;
else
  echo "Backing up database before changes";
  mkdir -p "$backup_dir";
  if ! mysqldump --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" | gzip > "$backup_file"; then
    echo "Unable to backup database '$sql_db'"
    exit 1
  fi
  # For some reason the above test is not failing when the dump fails. Need to check we actually got data.
  if [[ $(wc -c "$backup_file" | awk '{ print $1 }') -lt 1000 ]]; then
    echo "Unable to backup database '$sql_db'. Dump file seems too small."
    exit 1
  fi
  echo "Database backed up to '$backup_file'";
fi

echo "Updating database to new version";
error_int=0;
error_msg='';
# Run update scripts in release order, SQL first then PHP for each release
for release in $(echo "$version_list" | sed -n "$start","$end"p ); do
  echo "Running update $release";

  # Run SQL update scripts for this release
  for script in $(find "${site_root}/updates/$release" -type f  | grep -E "(.+\.sql|.+\.php)$" | sort); do
    # Check script listed in scripts_list actually exists
    if [ -f "$script" ]; then
      echo "[ ][$(date +%Y-%m-%d\ %H:%M:%S)] Starting script: $script";
      if [[ $script == *".sql" ]]; then
        # Check for and report errors
        if ! mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" < "$script"; then
          echo "[*][$(date +%Y-%m-%d\ %H:%M:%S)] Error running update: $script" >&2;
          error_int=1;
          error_msg="$error_msg,$script";
        else
          echo "[ ][$(date +%Y-%m-%d\ %H:%M:%S)] Success running update: $script";
        fi
      fi
      if [[ $script == *".php" ]]; then
        script_path=${script%/*};
        cd "$script_path" || exit 1;
        php_script=${script##*/};
        echo "$php_script";
        # Check for and report errors
        if ! php -f "$php_script" 2>&1; then
          cd "$current_dir" || exit 1;
          echo "[*][$(date +%Y-%m-%d\ %H:%M:%S)] Error running update: $script";
          error_int=1;
          error_msg="$error_msg,$script";
        else
          echo "[ ][$(date +%Y-%m-%d\ %H:%M:%S)] Success running update: $script";
        fi
        cd "$current_dir" || exit 1;
      fi
    fi
  done

  # Update the config table to show current release version
  mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -e "UPDATE \`config\` SET value='$release' WHERE name='site_version';";
done

echo "Backup of database located at: $backup_file";

# Reset facter fact
echo "$package""_update_error=0" > /etc/facter/facts.d/"$package"_update.txt;

if [ $error_int -ne 0 ]; then
  mkdir -p /etc/facter/facts.d/ >/dev/null 2>&1;
  error_msg=$(echo "$error_msg" | cut -d',' -f2-100);
  echo "$package""_update_error=$error_int" > /etc/facter/facts.d/"$package"_update.txt;
  echo "$package""_update_errormsg=$error_msg" >> /etc/facter/facts.d/"$package"_update.txt;
  echo "SQL updates completed with errors. Please manually fix issues or rollback to pre-update backup." >&2;
else
  echo "SQL updates completed";
fi
