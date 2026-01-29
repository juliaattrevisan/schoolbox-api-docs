#!/bin/bash
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
# IMPORTANT:
#   This file is under version control at https://github.com/alaress/puppet-alaress
#   and is managed by puppet. Any manual changes to this file will be overwritten.

# Constants
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
readonly INI_FILE='/etc/schoolbox/instances.ini';
readonly PRESERVED_CONFIG=(
  # Retrieve some of the Schoolbox config from MySQL using provided credentials
  "site_hostname" "site_domain" "amqp_user" "amqp_pass" "amqp_vhost"

  # External DB Config
  "external_db_host" "external_db_user" "external_db_passwd" "external_db_db" "external_portal" "external_db_encryption"

  # SAML Config
  "saml_sso_url" "saml_idp_metadata_url" "saml_enabled" "saml_slo_enabled" "saml_slo_url" "saml_logout_url" "saml_private_cert" "saml_public_cert" "saml_idp_certificate" "saml_entityid" "saml_sp_entityid"

  # LDAP Config
  "ldap_enable" "ldap_host" "ldap_user" "ldap_pass" "ldap_base" "ldap_host_alt" "ldap_user_alt" "ldap_base_alt" "ldap_username"

  #Learnosity Config
  "learnosity_consumer_key" "learnosity_consumer_secret" "learnosity_version"

  # Intellischools
  "intellischool_jwt_private_key" "intellischool_jwt_private_key_signing_algorithm" "intellischool_deployment_id" "intellischool_learner_dashboard_base_url" "intellischool_cohort_dashboard_base_url" "intellischool_deployment_secret"

  # Paperly
  "paperly_iframe_api_host" "paperly_iframe_api_key" "paperly_agent_ip_whitelist" "paperly_agent_secret" "paperly_agent_external_db_host_override" "paperly_agent_external_db_user_override" "paperly_agent_external_db_password_override" "paperly_agent_external_db_name_override" "paperly_open_in_new_tab"

  # Others
  "xml_dir" "file_dir" "otfs_verify_previous_file" "api_key" "otfs_s3_cred_key" "otfs_s3_cred_secret" "schooldata_import_frequency"
  "otfs_s3_alt_host" "otfs_s3_alt_bucket" "otfs_s3_alt_region" "otfs_s3_alt_bucket" "otfs_s3_alt_access_key" "otfs_s3_alt_secret_key"

  # Cloudwork
  "cloudwork_oauth_identity_provider" "cloudwork_oauth_client_id" "cloudwork_oauth_client_secret" "cloudwork_oauth_identifying_claims"

  # Other OAuth
  "oauth_issuer" "oauth_enable_sso" "oauth_response_type" "oauth_scopes"


  # Cloud specific
  "amqp_host" "redis_url" "redis_ephemeral_url" "taskd_redis_url" "clamd_host" "clamd_port"
)
readonly SITE_VERSION_SITE_TYPE_STG_CHANGED='21.0.10'  # Version where site_type 'dev' => 'stg'

# Global vars
declare source_sql_host
declare source_sql_user
declare source_sql_pass
declare source_sql_schema
declare dest_sql_host
declare dest_sql_user
declare dest_sql_pass
declare dest_sql_schema
declare to_instance_id;       # STG instance id we are syncing data to
declare alaress_password
declare -A sql_config=()
declare staging_backup_file   # Filename where we will temporarily store the staging backup. We will restore this if the script fails.


function usage () {
  echo "Usage : $0 --source-host [X] --source-user [X] --source-pass [X] --source-schema [X] --to-instance [Y]";
}

function log(){
  $LOG $L_INFO "$1"
}

# Log a normal message
function warn(){
  $LOG $L_WARN "$1"
}

function error(){
  $LOG $L_ERROR "$1"
}


# Parse CLI args
function parse_args() {
  local errors=0
  while [[ $# -gt 0 ]]; do
    case $1 in
      --source-host)
        source_sql_host="$2";
        shift; shift;
        ;;
      --source-user)
        source_sql_user="$2";
        shift; shift;
        ;;
      --source-pass)
        source_sql_pass="$2";
        shift; shift;
        ;;
      --source-schema)
        source_sql_schema="$2";
        shift; shift;
        ;;
      --to-instance)
        to_instance_id="$2"
        shift; shift;
        ;;
      *)
        # unknown option
        echo "Unrecognised option: '$1'"
        errors=1
        shift;
        ;;
    esac
  done

  if [[ $errors == 1 ]] || [[ -z $source_sql_host ]] || [[ -z $source_sql_user ]] || [[ -z $source_sql_pass ]] || [[ -z $source_sql_schema ]] || [[ -z $to_instance_id ]]; then
      usage
      exit 1
  fi
}


# Read SQL credentials from file
function get_dest_sql_credentials() {
  dest_sql_user=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_user);
  dest_sql_pass=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_passwd);
  dest_sql_host=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_host);
  dest_sql_schema=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_db);
}


# Read selected SQL config that should be preserved during the sync
read_dest_sql_config() {
  local config_value;
  local sql_command;
  for config_name in "${PRESERVED_CONFIG[@]}"; do
    sql_command="SELECT COUNT(*) FROM config WHERE name='$config_name'"
    if [[ $(mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -BNe "$sql_command" 2>/dev/null) != '1' ]]; then
      warn "No config found with name '$config_name'"
      continue;
    fi

    sql_command="SELECT ifnull(value, '') FROM config WHERE name='$config_name'"
    if ! config_value=$(mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -BNe "$sql_command" 2>/dev/null); then
      error "Unable to fetch value for config '$config_name'"
      exit 1
    fi
    sql_config[$config_name]="$config_value"
  done
  sql_command="SELECT password FROM user WHERE username = 'alaress' AND deleted_at IS NULL"
  if ! alaress_password=$(mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -BNe "$sql_command" 2>/dev/null); then
    echo "Unable to fetch alaress password'" >&2
    exit 1
  fi
}


# Ensure we only overwrite a staging instance
function check_dest_is_staging(){
  local site_type;
  local site_version
  local site_type_stg

  if ! site_type=$(mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -sse "SELECT value FROM config WHERE name = 'site_type'"); then
    error "Unable to fetch destination instance 'site_type'"
    exit 1;
  fi

  if ! site_version=$(mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -sse "SELECT value from config WHERE name = 'site_version'"); then
    error "Unable to fetch destination instance 'site_version'"
    exit 1;
  fi

  # If site_type is >= 21.0.10, site_type for staging instances is set to `stg` otherwise the value is set to `dev`.
  if [[ $(echo -e "$site_version\n$SITE_VERSION_SITE_TYPE_STG_CHANGED" | sort -V | head -n1) == "$SITE_VERSION_SITE_TYPE_STG_CHANGED" ]]; then
    site_type_stg=stg
  else
    site_type_stg=dev
  fi

  if [[ $site_type != "$site_type_stg" ]]; then
    error "This tool can only be used to overwrite staging instances. Current 'site_type': '$site_type'"
    exit 1;
  fi
}

#prd to stg db syncs should only be performed for the same organisations, if not same, exit
function check_source_and_dest_org_codes_match(){
  dest_org_code=$(mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -sse "SELECT value FROM config WHERE name = 'site_school_code'");
  source_org_code=$(mariadb -u "$source_sql_user" -p"$source_sql_pass" -h "$source_sql_host" "$source_sql_schema" -sse "SELECT value FROM config WHERE name = 'site_school_code'");

  if [[ $source_org_code != "$dest_org_code" ]]; then
    error "Error: source site_school_code '$source_org_code' does not match destination site_school_code '$dest_org_code'.
           This is likely because:
             1. The source and destination instances belong to different organisations, which is unsupported, or
             2. The destination site_school_code is corrupt. If so, this will need to be manually repaired before running this script."
    exit 1;
  fi
}


# Back up the staging database in case something goes wrong and we need to roll back
function backup_staging_database(){
  # Generate a unique filename
  staging_backup_file="/var/lib/schoolbox/stg_""$dest_sql_schema""_""$(date -I)"".sql";
  log "Backing up staging database '$dest_sql_schema' to '$staging_backup_file'"
  if ! mariadb-dump --single-transaction --no-tablespaces --add-drop-table -h "$dest_sql_host" --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" > "$staging_backup_file"; then
    error "Unable to dump staging database '$dest_sql_schema'"
    exit 1;
  fi
}


# Copy database from production to staging instance
function sync_database(){
  log "Fetching existing production database if not exists already";
  local prd_backup_file
  prd_backup_file="/var/lib/schoolbox/prd_""$source_sql_schema""_""$(date -I)"".sql";

  if [[ -f "$prd_backup_file" ]]; then
    log "Production database dump already exists. Re-using: '$prd_backup_file'"
  else
    if mariadb-dump --single-transaction --no-tablespaces --add-drop-table -h "$source_sql_host" --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u "$source_sql_user" -p"$source_sql_pass" "$source_sql_schema" > "$prd_backup_file"; then
      log "Production database dumped to '$prd_backup_file'";
    else
      error "Error: unable to backup production database to '$prd_backup_file'";
      exit 1;
    fi
  fi

  # Drop all database views
  log "Dropping all database views...";
  for view in $(mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" -s -e "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE LIKE \"VIEW\" AND TABLE_SCHEMA = \"$dest_sql_schema\""); do
    log "Dropping view '$view'...";
    if ! mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "SET MAX_STATEMENT_TIME=0; DROP view $view"; then
      error "Unable to drop view '$view'"
      exit 1;
    fi
  done;

  # Drop all tables
  log "Dropping all tables...";
  if ! mariadb-dump --single-transaction --no-tablespaces --no-data -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" | grep -e '^DROP \| FOREIGN_KEY_CHECKS' | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    error "Unable to drop tables"
    exit 1
  fi

  # Import production dump file into staging
  log "Importing production backup into staging database..."
  if (echo "SET MAX_STATEMENT_TIME=0; "; pv "$prd_backup_file") | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    log "Schema import successful, removing backup file: '$prd_backup_file'";
    rm -v "$prd_backup_file";
  else
    error "Error: unable to import production backup into staging database";
    restore_staging_database
    exit 1;
  fi;
}

update_log_dir(){
  mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "UPDATE config SET value = '/var/log/schoolbox/$to_instance_id' WHERE name = 'logs_dir'"
}

# Update data dirs in the staging instance database
function update_data_dirs(){
  if ! /usr/share/schoolbox/tools/generate-dir-sql.sh "/var/lib/schoolbox/$to_instance_id" | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    error "Error: unable to set *_dir paths";
    restore_staging_database;
    exit 1;
  fi;
}


# Restore selected config for this instance
restore_sql_config() {
  local sql_command;
  for config_name in "${!sql_config[@]}"; do
    sql_command="UPDATE config SET value='${sql_config[$config_name]}' WHERE name='$config_name'"
    if ! mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -BNe "$sql_command"; then
      error "Unable to set value for config '$config_name' to '${sql_config[$config_name]}'"
      restore_staging_database
      exit 1
    fi
  done
  sql_command="UPDATE user SET password = '$alaress_password' WHERE username = 'alaress' AND deleted_at IS NULL"
  if ! mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -BNe "$sql_command"; then
    error "Unable to set password for alaress user"
    restore_staging_database
    exit 1
  fi
}


# Localise the config table of the staging instance
function localise_instance(){
  local sql_command
  local site_version
  local site_type_stg
  declare -A local_config;
  declare -A hidden_config;
  declare -A editable_config;
  local_config["title"]="CONCAT('[STG] ',value)"
  local_config["mail_enable"]="'0'"
  local_config["mail_smtp_host"]="'127.0.0.1'"
  local_config["site_static_asset_serve_from_cdn"]="'1'"
  local_config["web_crawlers_enabled"]="'0'"
  local_config["mail_smtp_port"]="'1025'"
  hidden_config["mail_smtp_host"]="'false'"
  hidden_config["mail_smtp_port"]="'false'"
  editable_config["mail_smtp_host"]="'false'"
  editable_config["mail_smtp_port"]="'false'"
  editable_config["schooldata_import_frequency"]="'false'"

  # If site_type is >= 21.0.10, site_type for staging instances is set to `stg` otherwise the value is set to `dev`.
  if ! site_version=$(mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -sse "SELECT value from config WHERE name = 'site_version'"); then
    error "Unable to fetch destination instance 'site_version'"
    exit 1;
  fi
  if [[ $(echo -e "$site_version\n$SITE_VERSION_SITE_TYPE_STG_CHANGED" | sort -V | head -n1) == "$SITE_VERSION_SITE_TYPE_STG_CHANGED" ]]; then
    site_type_stg=stg
  else
    site_type_stg=dev
  fi
  local_config["site_type"]="'$site_type_stg'"

  for config_name in "${!local_config[@]}"; do
    sql_command="UPDATE config SET value=${local_config[$config_name]} WHERE name='$config_name'"
    if ! mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "$sql_command"; then
      error "Unable to update config '$config_name' to '${local_config[$config_name]}'"
      restore_staging_database
      exit 1
    fi
  done
  for config_name in "${!hidden_config[@]}"; do
    sql_command="UPDATE config SET hidden=${hidden_config[$config_name]} WHERE name='$config_name'"
    if ! mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "$sql_command"; then
      error "Unable to update config '$config_name' to '${hidden_config[$config_name]}'"
      restore_staging_database
      exit 1
    fi
  done
  for config_name in "${!editable_config[@]}"; do
    sql_command="UPDATE config SET editable=${editable_config[$config_name]} WHERE name='$config_name'"
    if ! mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "$sql_command"; then
      error "Unable to update config '$config_name' to '${editable_config[$config_name]}'"
      restore_staging_database
      exit 1
    fi
  done
}


# Truncates the staging instance's etherpad_pad table to ensure activity on the staging instance doesn't break production data
function truncate_etherpad(){
  # In cloud, etherpad + etherpad's redis are remote. So a naive copy will mean updates/deletes from staging will destroy production data.
  # In the future we will copy the data, so it will be safe.
  log "Truncating 'etherpad_pad' table...";
  if ! mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "SET MAX_STATEMENT_TIME=0; TRUNCATE etherpad_pad"; then
    error "Unable to truncate 'etherpad_pad' table..."
    restore_staging_database
    exit 1;
  fi
}


# Runs update scripts
function upgrade_instance(){
  if ! /usr/share/schoolbox/tools/site/upgrade-instances.sh --is-primary-host 1 --instance-id "$to_instance_id"; then
    restore_staging_database
    exit 1
  fi
}


# Restores the original staging database in the event something goes wrong during sync
function restore_staging_database(){
  log "Restoring staging database from '$staging_backup_file'"
  if (echo "SET MAX_STATEMENT_TIME=0; "; pv "$staging_backup_file") | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    log "Schema import successful, removing backup file: '$staging_backup_file'";
    rm -v "$staging_backup_file";
  else
    error "Error: unable to restore staging database";
    exit 1;
  fi;
}

# Sync plan-type with instance registry
function get_plan_type_from_instance_registry() {
  local -r instance_registry_url="$(schoolbox-instance-registry --url)";
  local -r puppet_cert="$(schoolbox-instance-registry --puppet-cert)";
  local -r api_key="$(schoolbox-instance-registry --api-key)";

  log "Fetching plan from IR..." >&2
  # send API request to IR - /api/hosts/{cert}/instances
  local -r ir_host_instances_json="$(curl \
    --silent \
    "$instance_registry_url/api/v2/host/$puppet_cert/instances?instance_id=$to_instance_id" \
    -H "Authorization: Bearer $api_key" \
    -H "Content-Type: application/json"
  )";

  echo "$ir_host_instances_json" | jq -r '.ir_host_instances | .[] | .plan_type';
}


function set_plan() {
  INSTANCE_ID="$to_instance_id" /usr/share/schoolbox/tools/set-plan.php --plan "$(get_plan_type_from_instance_registry)";
}


# Main script logic
function main() {
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  get_dest_sql_credentials
  read_dest_sql_config
  check_dest_is_staging
  check_source_and_dest_org_codes_match
  backup_staging_database
  sync_database
  update_log_dir
  update_data_dirs
  restore_sql_config
  localise_instance
  truncate_etherpad
  upgrade_instance
  localise_instance
  set_plan
  rm "$staging_backup_file"
}

main "$@";
