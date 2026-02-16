#!/usr/bin/env bash
# Restore a staging instance from a production host over SSH.
# - Supports interactive escalation approval (use --non-interactive to disable prompts)
# - DB-only by default
# - Full restore optionally syncs storage/files directories

set -eEuo pipefail

readonly INI_FILE='/etc/schoolbox/instances.ini'
readonly DEFAULT_SOURCE_HOST_SUFFIX='.prd'
readonly PRESERVED_CONFIG=(
  # Core site identity
  "site_hostname" "site_domain" "site_type" "title"

  # AMQP credentials
  "amqp_user" "amqp_pass" "amqp_vhost"

  # Redis config
  "redis_ephemeral_url" "redis_url" "taskd_redis_url"

  # External DB config
  "external_db_host" "external_db_user" "external_db_passwd" "external_db_db" "external_portal" "external_db_encryption"

  # SAML config
  "saml_sso_url" "saml_idp_metadata_url" "saml_enabled" "saml_slo_enabled" "saml_slo_url" "saml_logout_url" "saml_private_cert" "saml_public_cert" "saml_idp_certificate" "saml_entityid" "saml_sp_entityid"

  # LDAP config
  "ldap_enable" "ldap_host" "ldap_user" "ldap_pass" "ldap_base" "ldap_host_alt" "ldap_user_alt" "ldap_base_alt" "ldap_username"

  # OTFS config
  "otfs_adapter" "otfs_s3_cred_key" "otfs_s3_cred_secret" "otfs_verify_previous_file" "otfs_s3_alt_host" "otfs_s3_alt_bucket" "otfs_s3_alt_region" "otfs_s3_alt_access_key" "otfs_s3_alt_secret_key"

  # DR S3 backups
  "aws_s3_dr_backup_enabled" "aws_s3_dr_backup_visibility" "aws_s3_dr_sync_status"

  # Learnosity
  "learnosity_consumer_key" "learnosity_consumer_secret" "learnosity_version"

  # Geosnapshot
  "geosnapshot_api_key" "geosnapshot_enabled"

  # Intellischools
  "intellischool_jwt_private_key" "intellischool_jwt_private_key_signing_algorithm" "intellischool_deployment_id" "intellischool_learner_dashboard_base_url" "intellischool_cohort_dashboard_base_url" "intellischool_deployment_secret"

  # Paperly
  "paperly_iframe_api_host" "paperly_iframe_api_key" "paperly_agent_ip_whitelist" "paperly_agent_secret" "paperly_agent_external_db_host_override" "paperly_agent_external_db_user_override" "paperly_agent_external_db_password_override" "paperly_agent_external_db_name_override" "paperly_open_in_new_tab"

  # Cloudwork
  "cloudwork_oauth_identity_provider" "cloudwork_oauth_client_id" "cloudwork_oauth_client_secret" "cloudwork_oauth_identifying_claims"

  # Other OAuth
  "oauth_issuer" "oauth_enable_sso" "oauth_response_type" "oauth_scopes"

  # Data dir paths + misc
  "xml_dir" "file_dir" "api_key" "schooldata_import_frequency" "maintenance_mode"
)
readonly DISABLED_CONFIG=(
  "schooldata_import_frequency"
)

declare source_host=''
declare source_instance_id=''
declare to_instance_id="${INSTANCE_ID:-}"
declare backup_date=''
declare restore_mode='db'     # db|full
declare allow_escalation=0
declare escalation_approved=0
declare non_interactive=0

declare source_sql_user=''
declare source_sql_pass=''
declare source_sql_host=''
declare source_sql_schema=''

declare dest_sql_user=''
declare dest_sql_pass=''
declare dest_sql_host=''
declare dest_sql_schema=''

declare source_backup_dir=''
declare source_file_dir=''
declare source_storage_dir=''
declare source_data_dir=''
declare source_backup_file=''

declare dest_backup_dir=''
declare dest_file_dir=''
declare dest_storage_dir=''
declare dest_data_dir=''

declare prd_backup_file=''
declare staging_backup_file=''
declare staging_backup_ready=0
declare alaress_password_hex=''
declare -A sql_config=()

declare this_file
declare this_dir
declare site_root


function usage() {
  cat <<EOF
Usage:
  $0 [--source-host HOST] [--source-instance-id ID] [--to-instance ID] [--date YYYY-MM-DD] [--db-only|--full] [--allow-escalation] [--non-interactive]

Description:
  Restores a staging instance from SSH-accessible production backups.
  The script supports interactive escalation approval unless --non-interactive is used.

Optional args:
  --source-host           SSH host/alias for production. Defaults to lower(site_school_code)+'.prd' from destination DB.
  --source-instance-id    Optional source instance id validation. Source host must contain exactly one instance.
  --to-instance           Destination staging instance id. Falls back to INSTANCE_ID, or auto-detects if only one local instance exists.
  --date YYYY-MM-DD       Restore a specific backup date. If omitted, latest backup is used.
  --db-only               Restore database only (default)
  --full                  Restore database and sync storage/files
  --allow-escalation      Approve sudo-based privilege escalation
  --non-interactive       Disable interactive prompts. If escalation is needed, requires --allow-escalation.
  -h, --help              Show this help message
EOF
}

function log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1"
}

function warn() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARN: $1" >&2
}

function error() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

function can_use_pv() {
  command -v pv >/dev/null 2>&1 && [[ $non_interactive -ne 1 ]]
}

function can_use_rsync_copy_as() {
  rsync --help 2>/dev/null | grep -q -- '--copy-as='
}

function ensure_escalation_approved() {
  local response=''

  if [[ $escalation_approved -eq 1 ]]; then
    return 0
  fi

  if [[ $allow_escalation -eq 1 ]]; then
    escalation_approved=1
    log "Privilege escalation approved via --allow-escalation"
    return 0
  fi

  if [[ $non_interactive -eq 1 ]]; then
    error "Privilege escalation is required in --non-interactive mode."
    error "Re-run with --allow-escalation to approve escalation non-interactively."
    return 1
  fi

  echo "This full sync needs sudo escalation for rsync/write operations." >&2
  if ! read -r -p "Approve privilege escalation for this run? [y/N]: " response; then
    error "Unable to read interactive approval input."
    error "Re-run with --non-interactive --allow-escalation."
    return 1
  fi
  case "$response" in
    y|Y|yes|YES)
      escalation_approved=1
      log "Privilege escalation approved interactively"
      return 0
      ;;
    *)
      error "Privilege escalation was not approved"
      return 1
      ;;
  esac
}

function parse_args() {
  local errors=0

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --source-host)
        if [[ $# -lt 2 ]] || [[ -z ${2:-} ]]; then
          error "Missing value for --source-host"
          errors=1
          break
        fi
        source_host="$2"
        shift 2
        ;;
      --source-instance-id)
        if [[ $# -lt 2 ]] || [[ -z ${2:-} ]]; then
          error "Missing value for --source-instance-id"
          errors=1
          break
        fi
        source_instance_id="$2"
        shift 2
        ;;
      --to-instance)
        if [[ $# -lt 2 ]] || [[ -z ${2:-} ]]; then
          error "Missing value for --to-instance"
          errors=1
          break
        fi
        to_instance_id="$2"
        shift 2
        ;;
      --date)
        if [[ $# -lt 2 ]] || [[ -z ${2:-} ]]; then
          error "Missing value for --date"
          errors=1
          break
        fi
        backup_date="$2"
        shift 2
        ;;
      --db-only)
        restore_mode='db'
        shift
        ;;
      --full)
        restore_mode='full'
        shift
        ;;
      --allow-escalation)
        allow_escalation=1
        shift
        ;;
      --non-interactive)
        non_interactive=1
        shift
        ;;
      -h|--help)
        usage
        exit 0
        ;;
      *)
        error "Unrecognised option: '$1'"
        errors=1
        shift
        ;;
    esac
  done

  if [[ $errors -eq 1 ]]; then
    usage
    exit 1
  fi

  if [[ -n $backup_date ]]; then
    if ! [[ $backup_date =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
      error "Invalid --date format '$backup_date'. Expected YYYY-MM-DD."
      exit 1
    fi
    if ! date -d "$backup_date" '+%Y-%m-%d' >/dev/null 2>&1; then
      error "Invalid --date value '$backup_date'"
      exit 1
    fi
  fi
}

function require_command() {
  local cmd="$1"
  if ! command -v "$cmd" >/dev/null 2>&1; then
    error "Required command not found: '$cmd'"
    exit 1
  fi
}

function check_dependencies() {
  require_command ssh
  require_command scp
  require_command mariadb
  require_command mariadb-dump
  require_command schoolbox-read-instances

  if [[ $restore_mode == 'full' ]]; then
    require_command rsync
    require_command sudo
  fi
}

function resolve_source_host() {
  local site_school_code

  if [[ -n $source_host ]]; then
    log "Using source host '$source_host'"
    return 0
  fi

  if ! site_school_code=$(dest_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='site_school_code' LIMIT 1"); then
    error "Unable to read destination config 'site_school_code' while deriving default source host"
    exit 1
  fi

  site_school_code=$(echo "$site_school_code" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
  site_school_code=${site_school_code,,}
  if [[ -z $site_school_code ]]; then
    error "Destination config 'site_school_code' is empty; provide --source-host"
    exit 1
  fi

  source_host="${site_school_code}${DEFAULT_SOURCE_HOST_SUFFIX}"
  log "Using default source host '$source_host' derived from destination site_school_code"
}

function resolve_source_instance_id() {
  local derived_source_instance_id
  local instance_list
  local instance_count
  local q_ini

  q_ini=$(printf '%q' "$INI_FILE")
  if ! instance_list=$(source_ssh_cmd "schoolbox-read-instances --ini $q_ini --option instance-list"); then
    error "Unable to read source instance list from '$INI_FILE' on source host '$source_host'"
    exit 1
  fi

  instance_list=$(echo "$instance_list" | sed '/^[[:space:]]*$/d')
  if [[ -z $instance_list ]]; then
    error "No instances found in '$INI_FILE' on source host '$source_host'"
    exit 1
  fi

  instance_count=$(echo "$instance_list" | wc -l | tr -d ' ')
  if [[ $instance_count -ne 1 ]]; then
    error "Expected exactly one source instance on '$source_host', found '$instance_count'."
    error "Available source instances: $(echo "$instance_list" | tr '\n' ' ')"
    exit 1
  fi

  derived_source_instance_id=$(echo "$instance_list" | head -n1)
  if [[ -n $source_instance_id ]] && [[ $source_instance_id != "$derived_source_instance_id" ]]; then
    error "Provided --source-instance-id '$source_instance_id' does not match source host instance '$derived_source_instance_id'."
    exit 1
  fi

  source_instance_id="$derived_source_instance_id"
  log "Using source instance '$source_instance_id' from '$source_host:$INI_FILE'"
}

function resolve_to_instance_id() {
  local instance_list
  local instance_count

  if [[ -n $to_instance_id ]]; then
    log "Using destination instance '$to_instance_id'"
    return 0
  fi

  if ! instance_list=$(schoolbox-read-instances --ini "$INI_FILE" --option instance-list); then
    error "Unable to read instance list from '$INI_FILE'"
    exit 1
  fi

  instance_list=$(echo "$instance_list" | sed '/^[[:space:]]*$/d')
  if [[ -z $instance_list ]]; then
    error "No instances found in '$INI_FILE'"
    exit 1
  fi

  instance_count=$(echo "$instance_list" | wc -l | tr -d ' ')
  if [[ $instance_count -eq 1 ]]; then
    to_instance_id=$(echo "$instance_list" | head -n1)
    log "Derived destination instance '$to_instance_id' from '$INI_FILE'"
    return 0
  fi

  error "Multiple instances found in '$INI_FILE'. Provide --to-instance or set INSTANCE_ID."
  error "Available instances: $(echo "$instance_list" | tr '\n' ' ')"
  exit 1
}

function parse_script_paths() {
  this_file=$(readlink -f "$0")
  this_dir=${this_file%/*}
  site_root=$(readlink -f "$this_dir/../..")
}

function escape_sql_literal() {
  local value="$1"
  value=${value//\\/\\\\}
  value=${value//\'/\'\'}
  printf '%s' "$value"
}

function dest_mariadb_query() {
  local sql="$1"
  mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -BNe "$sql"
}

function dest_mariadb_exec() {
  local sql="$1"
  mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema" -e "$sql"
}

function dest_mariadb_server_exec() {
  local sql="$1"
  mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" -e "$sql"
}

function source_ssh_cmd() {
  local cmd="$1"
  ssh -o BatchMode=yes -o ConnectTimeout=30 "$source_host" "$cmd"
}

function get_source_ini_option() {
  local option="$1"
  local q_instance
  local q_ini
  local q_option

  q_instance=$(printf '%q' "$source_instance_id")
  q_ini=$(printf '%q' "$INI_FILE")
  q_option=$(printf '%q' "$option")
  source_ssh_cmd "INSTANCE_ID=$q_instance schoolbox-read-instances --ini $q_ini --option $q_option"
}

function source_mariadb_query() {
  local sql="$1"
  local q_user
  local q_pass
  local q_host
  local q_schema
  local q_sql

  q_user=$(printf '%q' "$source_sql_user")
  q_pass=$(printf '%q' "$source_sql_pass")
  q_host=$(printf '%q' "$source_sql_host")
  q_schema=$(printf '%q' "$source_sql_schema")
  q_sql=$(printf '%q' "$sql")

  source_ssh_cmd "mariadb -h $q_host -u $q_user -p$q_pass $q_schema -BNe $q_sql"
}

function get_dest_sql_credentials() {
  dest_sql_user=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_user)
  dest_sql_pass=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_passwd)
  dest_sql_host=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_host)
  dest_sql_schema=$(INSTANCE_ID="$to_instance_id" schoolbox-read-instances --ini "$INI_FILE" --option SQL_db)

  if [[ -z $dest_sql_user ]] || [[ -z $dest_sql_pass ]] || [[ -z $dest_sql_host ]] || [[ -z $dest_sql_schema ]]; then
    error "Unable to derive destination SQL credentials for instance '$to_instance_id'"
    exit 1
  fi
}

function get_source_sql_credentials() {
  source_sql_user=$(get_source_ini_option SQL_user)
  source_sql_pass=$(get_source_ini_option SQL_passwd)
  source_sql_host=$(get_source_ini_option SQL_host)
  source_sql_schema=$(get_source_ini_option SQL_db)

  if [[ -z $source_sql_user ]] || [[ -z $source_sql_pass ]] || [[ -z $source_sql_host ]] || [[ -z $source_sql_schema ]]; then
    error "Unable to derive source SQL credentials for instance '$source_instance_id' on host '$source_host'"
    exit 1
  fi
}

function check_dest_is_staging() {
  local site_type

  site_type=$(dest_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name = 'site_type' LIMIT 1")

  if [[ $site_type != 'stg' ]]; then
    error "This script only restores into staging instances. Expected site_type='stg', got '$site_type'."
    exit 1
  fi
}

function derive_dest_paths() {
  dest_backup_dir=$(dest_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='backup_dir' LIMIT 1")
  dest_file_dir=$(dest_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='file_dir' LIMIT 1")
  dest_storage_dir=$(dest_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='storage_dir' LIMIT 1")

  if [[ -n $dest_file_dir ]]; then
    dest_data_dir=${dest_file_dir%/*}
  elif [[ -n $dest_backup_dir ]]; then
    dest_data_dir=${dest_backup_dir%/*}
  else
    error "Unable to derive destination data dir from config values"
    exit 1
  fi

  if [[ -z $dest_storage_dir ]]; then
    dest_storage_dir="$dest_data_dir/storage"
  fi
}

function derive_source_paths() {
  source_backup_dir=$(source_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='backup_dir' LIMIT 1")
  source_file_dir=$(source_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='file_dir' LIMIT 1")
  source_storage_dir=$(source_mariadb_query "SELECT IFNULL(value, '') FROM config WHERE name='storage_dir' LIMIT 1")

  if [[ -n $source_file_dir ]]; then
    source_data_dir=${source_file_dir%/*}
  elif [[ -n $source_backup_dir ]]; then
    source_data_dir=${source_backup_dir%/*}
  else
    error "Unable to derive source data dir from source config values"
    exit 1
  fi

  if [[ -z $source_storage_dir ]]; then
    source_storage_dir="$source_data_dir/storage"
  fi
}

function read_dest_sql_config() {
  local config_name
  local config_exists
  local config_name_escaped
  local config_value_hex
  local sql_command

  for config_name in "${PRESERVED_CONFIG[@]}"; do
    config_name_escaped=$(escape_sql_literal "$config_name")
    sql_command="SELECT COUNT(*) FROM config WHERE name='$config_name_escaped'"
    if ! config_exists=$(dest_mariadb_query "$sql_command"); then
      error "Unable to check presence for config '$config_name'"
      exit 1
    fi
    if [[ $config_exists != '1' ]]; then
      warn "No config row found for '$config_name'"
      continue
    fi

    sql_command="SELECT HEX(IFNULL(value, '')) FROM config WHERE name='$config_name_escaped' LIMIT 1"
    if ! config_value_hex=$(dest_mariadb_query "$sql_command"); then
      error "Unable to fetch value for config '$config_name'"
      exit 1
    fi
    sql_config["$config_name"]="$config_value_hex"
  done

  if ! alaress_password_hex=$(dest_mariadb_query "SELECT HEX(password) FROM user WHERE username='alaress' AND deleted_at IS NULL LIMIT 1"); then
    error "Unable to fetch alaress password"
    exit 1
  fi
}

function backup_staging_database() {
  staging_backup_file=$(mktemp "/tmp/${to_instance_id}_${dest_sql_schema}_stg_backup_XXXXXX.sql")
  log "Backing up current destination DB to '$staging_backup_file'"
  if ! mariadb-dump \
    --single-transaction \
    --no-tablespaces \
    --add-drop-table \
    -h "$dest_sql_host" \
    --allow-keywords \
    --default-character-set=utf8mb4 \
    -i -e -q -a -c \
    -u "$dest_sql_user" \
    -p"$dest_sql_pass" \
    "$dest_sql_schema" > "$staging_backup_file"; then
    error "Unable to dump destination DB '$dest_sql_schema'"
    exit 1
  fi
  staging_backup_ready=1
}

function restore_staging_database() {
  local escaped_schema

  if [[ $staging_backup_ready -ne 1 ]] || [[ -z ${staging_backup_file:-} ]] || [[ ! -f $staging_backup_file ]]; then
    error "Rollback requested but destination backup is unavailable"
    return 1
  fi

  warn "Restore failed. Rolling destination DB back from '$staging_backup_file'"
  escaped_schema=${dest_sql_schema//\`/\`\`}
  if ! dest_mariadb_server_exec "SET MAX_STATEMENT_TIME=0; DROP DATABASE IF EXISTS \`$escaped_schema\`"; then
    error "Rollback failed while dropping destination schema '$dest_sql_schema'"
    return 1
  fi
  if ! dest_mariadb_server_exec "CREATE DATABASE \`$escaped_schema\` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"; then
    error "Rollback failed while recreating destination schema '$dest_sql_schema'"
    return 1
  fi

  if (echo "SET MAX_STATEMENT_TIME=0;"; if can_use_pv; then pv -f "$staging_backup_file"; else cat "$staging_backup_file"; fi) \
    | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" \
    | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    warn "Rollback completed"
    return 0
  fi

  error "Rollback failed. Destination DB may be inconsistent."
  return 1
}

function find_source_backup_file() {
  local selected_backup
  if ! selected_backup=$(ssh -o BatchMode=yes -o ConnectTimeout=30 "$source_host" bash -s -- "$source_data_dir" "$source_backup_dir" "$source_instance_id" "$backup_date" <<'EOF'
set -eEuo pipefail

source_data_dir="$1"
source_backup_dir="$2"
source_instance_id="$3"
backup_date="${4:-}"

declare -a search_dirs=()
declare -A seen_files=()
declare -a candidates=()
declare -a filtered=()

if [[ -n "$source_backup_dir" ]] && [[ -d "$source_backup_dir" ]]; then
  search_dirs+=("$source_backup_dir")
fi
if [[ -n "$source_data_dir" ]] && [[ -d "$source_data_dir/backups" ]]; then
  search_dirs+=("$source_data_dir/backups")
fi
if [[ ${#search_dirs[@]} -eq 0 ]] && [[ -n "$source_data_dir" ]] && [[ -d "$source_data_dir" ]]; then
  search_dirs+=("$source_data_dir")
fi

if [[ ${#search_dirs[@]} -eq 0 ]]; then
  exit 1
fi

for dir in "${search_dirs[@]}"; do
  if [[ "${dir##*/}" == "backups" ]]; then
    while IFS= read -r file; do
      [[ -f "$file" ]] || continue
      if [[ -z ${seen_files["$file"]+x} ]]; then
        seen_files["$file"]=1
        candidates+=("$file")
      fi
    done < <(find "$dir" -maxdepth 5 -type f \( -name "*.sql" -o -name "*.sql.gz" \) 2>/dev/null)
  else
    while IFS= read -r file; do
      [[ -f "$file" ]] || continue
      if [[ -z ${seen_files["$file"]+x} ]]; then
        seen_files["$file"]=1
        candidates+=("$file")
      fi
    done < <(find "$dir" -maxdepth 2 -type f \( -name "*.sql" -o -name "*.sql.gz" \) 2>/dev/null)
  fi
done

if [[ ${#candidates[@]} -eq 0 ]]; then
  exit 1
fi

for file in "${candidates[@]}"; do
  filename="${file##*/}"
  [[ "$filename" == *schoolbox* ]] || continue
  if [[ -n "$backup_date" ]] && [[ "$filename" != *"$backup_date"* ]]; then
    continue
  fi
  if [[ -n "$source_instance_id" ]] && [[ "$filename" != *"$source_instance_id"* ]] && [[ "$filename" != schoolbox_* ]]; then
    continue
  fi
  filtered+=("$file")
done

if [[ ${#filtered[@]} -eq 0 ]]; then
  exit 1
fi

selected=$(
  for file in "${filtered[@]}"; do
    ts=$(stat -c '%Y' "$file" 2>/dev/null || true)
    [[ -n "$ts" ]] && printf '%s %s\n' "$ts" "$file"
  done | sort -nr | head -n1 | cut -d' ' -f2-
)

if [[ -z "$selected" ]]; then
  exit 1
fi

echo "$selected"
EOF
  ); then
    if [[ -n $backup_date ]]; then
      error "Unable to find backup on source host '$source_host' for date '$backup_date'"
    else
      error "Unable to find any valid backup on source host '$source_host'"
    fi
    exit 1
  fi

  source_backup_file="$selected_backup"
  log "Selected source backup: '$source_backup_file'"
}

function download_source_backup() {
  local suffix='.sql'

  case "$source_backup_file" in
    *.sql.gz)
      suffix='.sql.gz'
      ;;
    *.sql)
      suffix='.sql'
      ;;
  esac

  prd_backup_file=$(mktemp "/tmp/${source_instance_id}_restore_XXXXXX${suffix}")
  log "Downloading source DB backup to '$prd_backup_file'"
  if ! scp -q -o BatchMode=yes -o ConnectTimeout=30 -- "$source_host:$source_backup_file" "$prd_backup_file"; then
    error "Unable to copy source backup '$source_host:$source_backup_file' to '$prd_backup_file'"
    exit 1
  fi
}

function ensure_safe_non_empty_path() {
  local path="$1"
  local label="$2"
  if [[ -z $path ]]; then
    error "Empty path provided for '$label'"
    exit 1
  fi
  if [[ $path == "/" ]]; then
    error "Refusing unsafe root path for '$label'"
    exit 1
  fi
}

function sync_remote_dir() {
  local source_dir="$1"
  local dest_dir="$2"
  local label="$3"
  local needs_sudo=0
  local current_user
  local invoking_user
  local q_source_dir
  local rsync_rsh
  local -a run_as_root=()

  ensure_safe_non_empty_path "$dest_dir" "$label destination"
  q_source_dir=$(printf '%q' "$source_dir")

  if ! source_ssh_cmd "test -d $q_source_dir"; then
    warn "Source $label dir does not exist on source host: '$source_dir' (skipping)"
    return 0
  fi

  if ! can_use_rsync_copy_as; then
    error "Installed rsync does not support '--copy-as'. Cannot run full sync without temp staging."
    exit 1
  fi

  current_user=$(id -un 2>/dev/null || true)
  invoking_user="${SUDO_USER:-$current_user}"
  if [[ -z $invoking_user ]]; then
    invoking_user="$current_user"
  fi

  if [[ $EUID -ne 0 ]] || [[ $invoking_user != 'root' ]]; then
    needs_sudo=1
  fi

  if [[ $needs_sudo -eq 1 ]] && ! ensure_escalation_approved; then
    exit 1
  fi

  if [[ $EUID -ne 0 ]]; then
    if ! command -v sudo >/dev/null 2>&1; then
      error "Full sync requires sudo to run rsync with '--copy-as=www-data'."
      exit 1
    fi
    if ! sudo -n true >/dev/null 2>&1; then
      error "Full sync requires passwordless sudo to run rsync with '--copy-as=www-data'."
      exit 1
    fi
    run_as_root=(sudo -n)
  fi

  if [[ $invoking_user != 'root' ]]; then
    rsync_rsh="sudo -n -u $invoking_user ssh -o BatchMode=yes -o ConnectTimeout=30"
  else
    rsync_rsh='ssh -o BatchMode=yes -o ConnectTimeout=30'
  fi

  if ! "${run_as_root[@]}" install -d -o www-data -g www-data "$dest_dir"; then
    error "Unable to prepare destination $label dir '$dest_dir' with owner www-data"
    exit 1
  fi

  log "Copying $label data from ssh $source_host : $source_dir/ to $dest_dir/"
  if ! "${run_as_root[@]}" rsync -rltHzv --delete --copy-as=www-data -e "$rsync_rsh" "$source_host:$source_dir/" "$dest_dir/" 2>&1 \
    | sed -u -E -e 's/\r//g' -e 's/^[[:space:]]+//'; then
    error "Unable to sync $label directory from source host"
    exit 1
  fi
}

function sync_full_data() {
  log "Performing Data Transfer"
  sync_remote_dir "$source_storage_dir" "$dest_storage_dir" "storage"

  if [[ -n $source_file_dir ]] && [[ -n $dest_file_dir ]]; then
    sync_remote_dir "$source_file_dir" "$dest_file_dir" "files"
  else
    warn "Unable to derive one of source/destination file_dir values. Skipping files sync."
  fi
}

function table_exists() {
  local table_name="$1"
  local escaped_schema
  local escaped_table
  local count

  escaped_schema=$(escape_sql_literal "$dest_sql_schema")
  escaped_table=$(escape_sql_literal "$table_name")
  count=$(dest_mariadb_query "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$escaped_schema' AND TABLE_NAME='$escaped_table'")
  [[ $count == '1' ]]
}

function drop_dest_schema_objects() {
  local escaped_schema

  escaped_schema=${dest_sql_schema//\`/\`\`}

  log "Dropping and recreating destination schema '$dest_sql_schema'"
  if ! dest_mariadb_server_exec "SET MAX_STATEMENT_TIME=0; DROP DATABASE IF EXISTS \`$escaped_schema\`"; then
    return 1
  fi
  if ! dest_mariadb_server_exec "CREATE DATABASE \`$escaped_schema\` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"; then
    return 1
  fi
}

function stream_backup_file() {
  local uncompressed_size=''

  if [[ $prd_backup_file == *.gz ]]; then
    if can_use_pv; then
      # Track progress on uncompressed SQL stream (what is actually loaded into DB).
      uncompressed_size=$(gzip -l "$prd_backup_file" 2>/dev/null | awk 'NR==2 {print $2}')
      if [[ $uncompressed_size =~ ^[0-9]+$ ]] && [[ $uncompressed_size -gt 0 ]]; then
        gunzip -c "$prd_backup_file" | pv -f -s "$uncompressed_size"
      else
        gunzip -c "$prd_backup_file" | pv -f
      fi
    else
      gunzip -c "$prd_backup_file"
    fi
  else
    if can_use_pv; then
      pv -f "$prd_backup_file"
    else
      cat "$prd_backup_file"
    fi
  fi
}

function sync_database() {
  if [[ -z ${prd_backup_file:-} ]] || [[ ! -f $prd_backup_file ]]; then
    error "Downloaded source backup file not found: '$prd_backup_file'"
    exit 1
  fi

  if ! drop_dest_schema_objects; then
    error "Unable to reset destination schema '$dest_sql_schema'"
    restore_staging_database
    exit 1
  fi

  log "Importing source backup into destination schema '$dest_sql_schema'"
  if ! (echo "SET MAX_STATEMENT_TIME=0;"; stream_backup_file) \
    | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" \
    | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    error "Unable to import source backup into destination schema '$dest_sql_schema'"
    restore_staging_database
    exit 1
  fi
}

function update_local_paths() {
  log "Updating destination data dir config values for '$dest_data_dir'"
  if ! "$site_root/tools/generate-dir-sql.sh" "$dest_data_dir" \
    | mariadb -h "$dest_sql_host" -u "$dest_sql_user" -p"$dest_sql_pass" "$dest_sql_schema"; then
    error "Unable to update destination *_dir config values"
    restore_staging_database
    exit 1
  fi

  if ! dest_mariadb_exec "UPDATE config SET value='/var/log/schoolbox/$to_instance_id' WHERE name='logs_dir'"; then
    error "Unable to set destination logs_dir"
    restore_staging_database
    exit 1
  fi
}

function restore_sql_config() {
  local config_name
  local config_name_escaped
  local value_hex

  for config_name in "${!sql_config[@]}"; do
    config_name_escaped=$(escape_sql_literal "$config_name")
    value_hex="${sql_config[$config_name]}"
    if ! dest_mariadb_exec "UPDATE config SET value=UNHEX('$value_hex') WHERE name='$config_name_escaped'"; then
      error "Unable to restore config '$config_name'"
      restore_staging_database
      exit 1
    fi
  done

  if [[ -n $alaress_password_hex ]]; then
    if ! dest_mariadb_exec "UPDATE user SET password=UNHEX('$alaress_password_hex') WHERE username='alaress' AND deleted_at IS NULL"; then
      error "Unable to restore alaress user password"
      restore_staging_database
      exit 1
    fi
  fi

  for config_name in "${DISABLED_CONFIG[@]}"; do
    config_name_escaped=$(escape_sql_literal "$config_name")
    if ! dest_mariadb_exec "UPDATE config SET editable='false' WHERE name='$config_name_escaped'"; then
      error "Unable to disable editing for config '$config_name'"
      restore_staging_database
      exit 1
    fi
  done
}

function localise_non_prod_defaults() {
  log "Applying non-production safety defaults"

  # Keep parity with restore.sh staging behavior.
  if ! dest_mariadb_exec "UPDATE config SET value=CONCAT('[Staging] ', IFNULL(value, '')) WHERE name='title' AND LEFT(IFNULL(value, ''), 9) != '[Staging]'"; then
    warn "Unable to apply [Staging] title prefix"
  fi
  if ! dest_mariadb_exec "UPDATE config SET value='0' WHERE name='web_crawlers_enabled'"; then
    warn "Unable to set web_crawlers_enabled=0"
  fi
  if ! dest_mariadb_exec "UPDATE config SET value='127.0.0.1' WHERE name='mail_smtp_host'"; then
    warn "Unable to set mail_smtp_host"
  fi
  if ! dest_mariadb_exec "UPDATE config SET value='1025' WHERE name='mail_smtp_port'"; then
    warn "Unable to set mail_smtp_port"
  fi
  if ! dest_mariadb_exec "UPDATE config SET value='1' WHERE name='mail_enable'"; then
    warn "Unable to set mail_enable"
  fi
  if ! dest_mariadb_exec "UPDATE config SET value='1' WHERE name='debug_show_stack_trace'"; then
    warn "Unable to set debug_show_stack_trace=1"
  fi
}

function truncate_post_restore_tables() {
  if table_exists "mobile_app_registry"; then
    log "Truncating 'mobile_app_registry'."
    dest_mariadb_exec "SET MAX_STATEMENT_TIME=0; TRUNCATE mobile_app_registry"
  fi

  if [[ $restore_mode == 'db' ]] && table_exists "storage_transform"; then
    log "DB-only restore complete. Truncating 'storage_transform'."
    dest_mariadb_exec "SET MAX_STATEMENT_TIME=0; TRUNCATE storage_transform"
  fi

  if table_exists "etherpad_pad"; then
    log "Truncating 'etherpad_pad' to protect shared-host data integrity."
    dest_mariadb_exec "SET MAX_STATEMENT_TIME=0; TRUNCATE etherpad_pad"
  fi
}

function cleanup_temp_files() {
  if [[ -n ${prd_backup_file:-} ]] && [[ -f $prd_backup_file ]]; then
    rm -f "$prd_backup_file" || warn "Unable to remove temp source backup '$prd_backup_file'"
  fi
  if [[ -n ${staging_backup_file:-} ]] && [[ -f $staging_backup_file ]]; then
    rm -f "$staging_backup_file" || warn "Unable to remove staging DB backup '$staging_backup_file'"
  fi
}

function main() {
  export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games'

  parse_script_paths
  parse_args "$@"
  check_dependencies
  resolve_to_instance_id
  get_dest_sql_credentials
  check_dest_is_staging
  resolve_source_host
  resolve_source_instance_id
  trap cleanup_temp_files EXIT

  log "Starting SSH restore: source '$source_host/$source_instance_id' -> destination '$to_instance_id' (mode: $restore_mode)"
  source_ssh_cmd "true" >/dev/null

  derive_dest_paths
  read_dest_sql_config

  get_source_sql_credentials
  derive_source_paths
  find_source_backup_file
  download_source_backup

  backup_staging_database
  if [[ $restore_mode == 'full' ]]; then
    sync_full_data
  fi

  sync_database
  update_local_paths
  restore_sql_config
  localise_non_prod_defaults
  truncate_post_restore_tables

  log "Restore complete"
}

main "$@"
