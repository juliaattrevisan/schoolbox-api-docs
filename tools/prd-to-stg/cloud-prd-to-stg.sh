#!/bin/bash
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

set -eEuo pipefail

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
  "xml_dir" "file_dir" "otfs_verify_previous_file" "api_key" "otfs_s3_cred_key" "otfs_s3_cred_secret" "otfs_s3_bucket" "otfs_s3_region" "schooldata_import_frequency"
  "otfs_s3_alt_host" "otfs_s3_alt_bucket" "otfs_s3_alt_region" "otfs_s3_alt_access_key" "otfs_s3_alt_secret_key"

  # Cloudwork
  "cloudwork_oauth_identity_provider" "cloudwork_oauth_client_id" "cloudwork_oauth_client_secret" "cloudwork_oauth_identifying_claims"

  # Other OAuth
  "oauth_issuer" "oauth_enable_sso" "oauth_response_type" "oauth_scopes"


  # Cloud specific
  "amqp_host" "redis_url" "redis_ephemeral_url" "taskd_redis_url" "clamd_host" "clamd_port"
)
readonly SITE_VERSION_SITE_TYPE_STG_CHANGED='21.0.10'  # Version where site_type 'dev' => 'stg'
readonly DAILY_S3_PREFIX='backups/daily/'

# Global vars
declare dest_sql_host
declare dest_sql_user
declare dest_sql_pass
declare dest_sql_schema
declare source_instance_id
declare to_instance_id;       # STG instance id we are syncing data to
declare alaress_password
declare -A sql_config=()
declare staging_backup_file   # Filename where we store the staging backup. We will restore this if the script fails.
declare prd_backup_file
declare staging_backup_ready=0
declare s3_cred_key
declare s3_cred_secret
declare s3_region
declare s3_bucket
declare s3_alt_host
declare s3_alt_bucket
declare s3_alt_region
declare s3_alt_access_key
declare s3_alt_secret_key
declare plan_type


function usage () {
  echo "Usage: $0 --source-instance-id [X] --to-instance [Y] [--plan PLAN_TYPE]"
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

function is_tty(){
  [[ -t 1 ]] && [[ -t 2 ]]
}

function can_use_pv(){
  command -v pv >/dev/null 2>&1 && is_tty
}


# Parse CLI args
function parse_args() {
  local errors=0
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --source-instance-id)
        if [[ $# -lt 2 ]] || [[ -z $2 ]]; then
          echo "Missing value for --source-instance-id"
          errors=1
          break
        fi
        source_instance_id="$2";
        shift; shift;
        ;;
      --to-instance)
        if [[ $# -lt 2 ]] || [[ -z $2 ]]; then
          echo "Missing value for --to-instance"
          errors=1
          break
        fi
        to_instance_id="$2"
        shift; shift;
        ;;
      --plan)
        if [[ $# -lt 2 ]] || [[ -z $2 ]]; then
          echo "Missing value for --plan"
          errors=1
          break
        fi
        plan_type="$2"
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

  if [[ $errors == 1 ]] || [[ -z ${source_instance_id:-} ]] || [[ -z ${to_instance_id:-} ]]; then
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

function read_dest_s3_config(){
  local sql_command
  local config_value
  local -r config_names=(
    "otfs_s3_cred_key" "otfs_s3_cred_secret" "otfs_s3_region" "otfs_s3_bucket"
    "otfs_s3_alt_host" "otfs_s3_alt_bucket" "otfs_s3_alt_region" "otfs_s3_alt_access_key" "otfs_s3_alt_secret_key"
  )

  for config_name in "${config_names[@]}"; do
    sql_command="SELECT ifnull(value, '') FROM config WHERE name='$config_name'"
    if ! config_value=$(mariadb -u "$dest_sql_user" -p"$dest_sql_pass" -h "$dest_sql_host" "$dest_sql_schema" -BNe "$sql_command" 2>/dev/null); then
      error "Unable to fetch value for config '$config_name'"
      exit 1
    fi
    case "$config_name" in
      otfs_s3_cred_key) s3_cred_key="$config_value";;
      otfs_s3_cred_secret) s3_cred_secret="$config_value";;
      otfs_s3_region) s3_region="$config_value";;
      otfs_s3_bucket) s3_bucket="$config_value";;
      otfs_s3_alt_host) s3_alt_host="$config_value";;
      otfs_s3_alt_bucket) s3_alt_bucket="$config_value";;
      otfs_s3_alt_region) s3_alt_region="$config_value";;
      otfs_s3_alt_access_key) s3_alt_access_key="$config_value";;
      otfs_s3_alt_secret_key) s3_alt_secret_key="$config_value";;
    esac
  done

  if [[ -z $s3_cred_key ]] || [[ -z $s3_cred_secret ]] || [[ -z $s3_region ]] || [[ -z $s3_bucket ]]; then
    error "Missing S3 configuration in destination config table"
    exit 1
  fi
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
    error "Unable to fetch alaress password"
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

# Back up the staging database in case something goes wrong and we need to roll back
function backup_staging_database(){
  local backup_template
  backup_template="/var/lib/schoolbox/stg_${dest_sql_schema}_$(date +%Y-%m-%dT%H-%M-%S)_XXXXXX.sql"
  if ! staging_backup_file=$(mktemp "$backup_template"); then
    error "Unable to create temporary backup file for staging database '$dest_sql_schema'"
    exit 1;
  fi

  log "Backing up staging database '$dest_sql_schema' to '$staging_backup_file'"
  if ! mariadb-dump --single-transaction --no-tablespaces --add-drop-table -h "$dest_sql_host" --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" > "$staging_backup_file"; then
    error "Unable to dump staging database '$dest_sql_schema'"
    exit 1;
  fi
  staging_backup_ready=1
}


# Copy database from production to staging instance
function sync_database(){
  if [[ -z ${prd_backup_file:-} ]] || [[ ! -f $prd_backup_file ]]; then
    error "Production backup file not found: '$prd_backup_file'"
    exit 1
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
  if (echo "SET MAX_STATEMENT_TIME=0;"; if can_use_pv; then pv "$prd_backup_file" | gunzip -c; else gunzip -c "$prd_backup_file"; fi) | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    log "Schema import successful, removing backup file: '$prd_backup_file'";
    rm -v "$prd_backup_file";
  else
    error "Error: unable to import production backup into staging database";
    restore_staging_database
    exit 1;
  fi;
}

update_log_dir(){
  if ! mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "UPDATE config SET value = '/var/log/schoolbox/$to_instance_id' WHERE name = 'logs_dir'"; then
    error "Error: unable to update logs_dir config"
    restore_staging_database
    exit 1
  fi
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
  if [[ $staging_backup_ready -ne 1 ]] || [[ -z ${staging_backup_file:-} ]] || [[ ! -f $staging_backup_file ]]; then
    error "Rollback requested but staging backup is unavailable"
    return 1
  fi

  log "Restoring staging database from '$staging_backup_file'"
  if (echo "SET MAX_STATEMENT_TIME=0; "; if can_use_pv; then pv "$staging_backup_file"; else cat "$staging_backup_file"; fi) | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    log "Schema import successful, removing backup file: '$staging_backup_file'";
    rm -v "$staging_backup_file";
  else
    error "Error: unable to restore staging database";
    return 1;
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

  if [[ -z $ir_host_instances_json ]]; then
    error "Instance registry returned an empty response"
    return 1
  fi

  echo "$ir_host_instances_json" | jq -r '.ir_host_instances | .[] | .plan_type';
}


function set_plan() {
  if [[ -z $plan_type ]]; then
    if ! plan_type=$(get_plan_type_from_instance_registry); then
      error "Unable to fetch plan type from instance registry"
      restore_staging_database
      exit 1
    fi
  fi
  if ! INSTANCE_ID="$to_instance_id" /usr/share/schoolbox/tools/set-plan.php --plan "$plan_type"; then
    error "Unable to set plan '$plan_type' for instance '$to_instance_id'"
    restore_staging_database
    exit 1
  fi
}

function build_aws_env(){
  # Always use explicit credentials from DB config. If an inherited session token
  # is stale, S3 ListObjectsV2 calls fail with InvalidToken.
  unset AWS_SESSION_TOKEN AWS_SECURITY_TOKEN AWS_PROFILE AWS_DEFAULT_PROFILE

  if [[ -n $s3_alt_host ]] && [[ -n $s3_alt_bucket ]]; then
    export AWS_ACCESS_KEY_ID="$s3_alt_access_key"
    export AWS_SECRET_ACCESS_KEY="$s3_alt_secret_key"
    export AWS_DEFAULT_REGION="$s3_alt_region"
  else
    export AWS_ACCESS_KEY_ID="$s3_cred_key"
    export AWS_SECRET_ACCESS_KEY="$s3_cred_secret"
    export AWS_DEFAULT_REGION="$s3_region"
  fi
}

function aws_endpoint_args(){
  if [[ -n $s3_alt_host ]] && [[ -n $s3_alt_bucket ]]; then
    echo "--endpoint-url $s3_alt_host"
  else
    echo ""
  fi
}

function get_s3_bucket(){
  if [[ -n $s3_alt_host ]] && [[ -n $s3_alt_bucket ]]; then
    echo "$s3_alt_bucket"
  else
    echo "$s3_bucket"
  fi
}

function find_latest_backup_key(){
  local bucket
  local aws_args
  local latest_key
  bucket=$(get_s3_bucket)
  aws_args=$(aws_endpoint_args)
  if ! latest_key=$(aws s3 ls "s3://$bucket/${DAILY_S3_PREFIX}${source_instance_id}_schoolbox_" $aws_args \
    | tr -s ' ' \
    | cut -d' ' -f4 \
    | grep -E "^${source_instance_id}_schoolbox_[0-9]{4}-[0-9]{2}-[0-9]{2}\.sql\.gz$" \
    | sort \
    | tail -n1); then
    return 1
  fi
  if [[ -z $latest_key ]]; then
    return 1
  fi
  echo "${DAILY_S3_PREFIX}${latest_key}"
}

function download_prd_backup(){
  local bucket
  local aws_args
  local selected_key
  local backup_date

  build_aws_env
  bucket=$(get_s3_bucket)
  aws_args=$(aws_endpoint_args)
  if ! selected_key=$(find_latest_backup_key); then
    error "Unable to locate a daily backup in S3 for instance '$source_instance_id'"
    exit 1
  fi

  backup_date=$(basename "$selected_key" | sed -n "s/^${source_instance_id}_schoolbox_\([0-9]\{4\}-[0-9]\{2\}-[0-9]\{2\}\)\.sql\.gz$/\1/p")
  if [[ -z $backup_date ]]; then
    error "Unable to determine backup date from key '$selected_key'"
    exit 1
  fi

  prd_backup_file="/var/lib/schoolbox/${source_instance_id}_schoolbox_${backup_date}.sql.gz"
  if ! aws s3 cp "s3://$bucket/$selected_key" "$prd_backup_file" $aws_args; then
    error "Unable to download production backup from S3: s3://$bucket/$selected_key"
    exit 1
  fi
  log "Downloaded production backup: s3://$bucket/$selected_key"
}

# Main script logic
function main() {
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  get_dest_sql_credentials
  read_dest_s3_config
  read_dest_sql_config
  check_dest_is_staging
  download_prd_backup
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
  if ! rm "$staging_backup_file"; then
    warn "Unable to remove staging backup file '$staging_backup_file'"
  fi
}

main "$@";
