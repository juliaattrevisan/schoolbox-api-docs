#!/usr/bin/env bash
# Provisions the Docker environment: port of `vagrant-provision.sh`

# Variables which must be provided in docker-consts.sh
declare SITE_ROOT
declare CACHE_DIR
declare REQUIRED_CONSTS=(SITE_ROOT CACHE_DIR)

# Variables which must be provided directly via env vars
declare CONFIG_DATE_TIMEZONE
declare INSTANCE_ID
declare REQUIRED_ENV_VARS=(CONFIG_DATE_TIMEZONE INSTANCE_ID)

# Variables which must be provided in the config files
# docker.env and docker.default.env
declare DATA_SOURCE
declare DATA_FILENAME
declare CERTIFICATE_SOURCE
declare EXTERNAL_DB
declare EXTERNAL_DB_HOST
declare EXTERNAL_DB_DB
declare INSTANCE_REGISTRY_URL
declare ARTIFACT_SOURCE
declare REQUIRED_CONFIGS=(\
  DATA_SOURCE DATA_FILENAME \
  CERTIFICATE_SOURCE \
  EXTERNAL_DB EXTERNAL_DB_HOST EXTERNAL_DB_DB \
  INSTANCE_REGISTRY_URL \
  ARTIFACT_SOURCE \
)

function verify_args() {
  local return=1
  for var in "$@"; do
    if [ -v ${var} ]; then
      log "${var}: ${!var}";
    else
      warn "${var}: not defined"
      return=0
    fi
  done
  return $return
}

function verify_folders() {
  local return=1
  for var in "$@"; do
    if [ -d "${!var}" ]; then
      log "${var}: ${!var} is a folder";
    else
      warn "${var}: ${!var} is not a folder: attempting to create it";
      if [[ "$(mkdir -p "${!var}" 2>/dev/null)" -eq 0 ]]; then
        log "${var}: ${!var} created";
      else
        error "${var}: ${!var} could not be created";
        return=0
      fi
    fi
  done
  return $return
}

function load_consts() {
  # Load global constants and functions:
  # SITE_ROOT is not yet defined, so assume this script is running in what will
  # eventually be SITE_ROOT
  source ./tools/docker-consts.sh
}

function verify_env_vars() {
  log "Verifying env vars:"
  local return=1
  verify_args "${REQUIRED_ENV_VARS[@]}"
  if [ $? -eq 0 ]; then
    error "Not all required env vars defined: exiting"
    exit 1
  fi
  echo ""
}

function verify_consts() {
  log "Verifying constants:"
  local return=1
  verify_args "${REQUIRED_CONSTS[@]}"
  if [ $? -eq 0 ]; then
    error "Not all required consts defined: exiting"
    exit 1
  fi
  echo ""

  log "Verifying constants are folders:"
  verify_folders "${REQUIRED_CONSTS[@]}"
  if [ $? -eq 0 ]; then
    error "Not all required consts are folders: exiting"
    exit 1
  fi
  echo ""
}

function load_and_verify_config() {
  # Global vars: load from .ini files
  source "${SITE_ROOT}/docker.default.env"
  if [ -f "${SITE_ROOT}/docker.env" ]; then
    source "${SITE_ROOT}/docker.env"
  fi

  log "Verifying configuration:"
  verify_args "${REQUIRED_CONFIGS[@]}"
  if [ $? -eq 0 ]; then
    error "Not all required configs defined: exiting"
    exit 1
  fi
}

function setup_aws_sso() {
  # AWS is already configured in ~/.aws/config:
  # just test if credentials in env, ~/.aws/credentials or wherever are current.
  log "Testing if logged in to AWS..."
  if ! aws configure list > /dev/null; then
    log "Not logged in to AWS: logging in"
    aws sso login --no-browser --use-device-code
  else
    log "Logged in to AWS: continuing"
  fi
}

function cleanup_cache_dir() {
  log "Cleaning up zero byte files";
  find "${CACHE_DIR}" -type f -empty -delete;
}

function download_secrets() {
  log "Retrieving latest secrets";
  # Download some files from S3: note these still use the Vagrant names
  if ! $SITE_ROOT/tools/download-s3-file.sh --data-source "$DATA_SOURCE"  --file-name "vagrant-secrets.sql" --cache-dir "$CACHE_DIR"; then
    error "Unable to download s3 file: 'vagrant-secrets.sql': exiting";
    exit 1;
  fi
  if ! $SITE_ROOT/tools/download-s3-file.sh --data-source "$DATA_SOURCE" --file-name "vagrant-cleanup-secrets.sql" --cache-dir "$CACHE_DIR"; then
    error "Unable to download s3 file: 'vagrant-cleanup-secrets.sql': exiting";
    exit 1;
  fi
}

function download_certs() {
    log "Retrieving latest wildcard certificate";
    local filename='tls-wildcard.dev.schoolbox.cloud.tar.gz'

    if ! $SITE_ROOT/tools/download-s3-file.sh --data-source "${CERTIFICATE_SOURCE}" \
      --file-name "certs/$filename" \
      --cache-dir "$CACHE_DIR"; then
      error "Unable to download wildcard certificate: exiting"
      exit 1
    fi
    tar xzfO "$CACHE_DIR/$filename" dev.schoolbox.cloud/fullchain.pem > /etc/nginx/certs/schoolbox-docker-fullchain.crt
    tar xzfO "$CACHE_DIR/$filename" dev.schoolbox.cloud/privkey.pem > /etc/nginx/certs/schoolbox-docker-private.key
    tar xzfO "$CACHE_DIR/$filename" dev.schoolbox.cloud/chain.pem > /etc/nginx/certs/schoolbox-docker-intermediate.crt
}

function mark_safe_for_git() {
  log "Adding Schoolbox dir ${SITE_ROOT} as safe for Git";
  git config --global --add safe.directory "${SITE_ROOT}"
}

function init_database() {
  # Timezone data: should be run in mariadb container on setup
  #log "Updating timezone data";
  #mysql_tzinfo_to_sql /usr/share/zoneinfo 2>/dev/null | mysql --defaults-extra-file=/root/.my.cnf mysql 2>/dev/null;

  log "Creating Schoolbox MariaDB schema and user";
  mariadb -h mariadb -u root < "${SITE_ROOT}/docker/schoolbox/sql/setup.sql"
}

function download_artifact() {
  # Name of the artifact to download
  local artifact_name=$1

  # Name of the cache
  local cache_name=$2

  # Location of the lock file to check
  local lock_file=$3

  # Location of the checksum
  local checksum_file=$4

  # Location to extract to
  local extract_dir=$5

  local lock_checksum;
#  echo "sha1sum \"${SITE_ROOT}/${lock_file}\" 2>/dev/null | awk '{print \$1}'"
  lock_checksum=`sha1sum "${SITE_ROOT}/${lock_file}" 2>/dev/null | awk '{print $1}'`;

  local artifact_file='';
  local current_checksum;
  if [ -f "$SITE_ROOT/${checksum_file}" ]; then
#      echo "awk '{print \$1}' \"${SITE_ROOT}/${checksum_file}\""
      current_checksum=`awk '{print $1}' "${SITE_ROOT}/${checksum_file}"`;
      if [ "$lock_checksum" == "$current_checksum" ]; then
          log "Artifact $artifact_name up to date, nothing to change";
          artifact_file='ignore';
      fi
  fi

  local cache_file;
  local etag_file;
  cache_file="${CACHE_DIR}/${cache_name}.cache"
  etag_file="${CACHE_DIR}/${cache_name}.etag.cache"

  if [ "$artifact_file" != 'ignore' ]; then
      log "Artifact $artifact_name not up to date: checking for newer version";
      # download files
      local check;
      check=`grep "$lock_checksum" "$cache_file" 2>/dev/null | wc -l`;
      if [ $check -gt 0 ]; then
          # We've located a matching cached file
          artifact_file=`grep "$lock_checksum" "$cache_file" 2>/dev/null | head -n1 | cut -d' ' -f2-100`;
      else
          # No cached files match: check to see if there are new versions
          log "No matching cached artifact file found: downloading";

          for branch in develop master;
          do
              # Check if we've already found a matching file
              if [ "$artifact_file" == '' ];
              then
                  local etag;
                  etag=$(curl -I "${ARTIFACT_SOURCE}/$branch-${artifact_name}" 2>/dev/null | grep ETag | cut -d'"' -f2);
                  if [ $? -eq 0 ];
                  then
                      local check_etag;
                      check_etag=`grep $etag $etag_file 2>/dev/null | wc -l`;
                      if [ $check_etag -eq 0 ];
                      then
                          # New/updated version available - download it
                          local version_artifact_name;
                          version_artifact_name="${branch}-${artifact_name}"
                          log "Downloading latest artifact: ${version_artifact_name}";
                          wget --no-verbose "${ARTIFACT_SOURCE}/${version_artifact_name}" -O "${CACHE_DIR}/${version_artifact_name}" 2>&1;
                          if [ $? -eq 0 ]; then
                              # This is a new file, so remove old references
                              sed -i "/${version_artifact_name}/d" "$etag_file" 2>/dev/null;
                              sed -i "/${version_artifact_name}/d" "$cache_file" 2>/dev/null;
                              # Add new file to etag cache
                              echo "$etag ${version_artifact_name}" >> "$etag_file";

                              # Cleanup for purity?
                              rm -fR "${CACHE_DIR}/${extract_dir:?}" 2> /dev/null;

                              # Extract checksum
                              tar xzf "${CACHE_DIR}/${version_artifact_name}" "${checksum_file}";
                              local version_artifact_checksum
                              version_artifact_checksum=`awk '{print $1}' "${extract_dir}/${checksum_file}"`;

                              # Cleanup again
                              rm -fR "${CACHE_DIR}/${extract_dir:?}" 2> /dev/null;

                              # Add new file to vendor cache
                              echo "$version_artifact_checksum ${version_artifact_name}" >> "$cache_file";
                              # Check if this is file we need
                              if [ "$version_artifact_checksum" == "$lock_checksum" ]; then
                                  artifact_file="${version_artifact_name}";
                              fi
                          fi
                      fi
                  fi
              fi
          done
      fi
  fi

  echo "$artifact_file"
}

function init_composer_dependencies() {
  local vendor_dir="$SITE_ROOT/vendor"
  local lock_checksum
  lock_checksum=$(sha1sum "${SITE_ROOT}/composer.lock" 2>/dev/null | awk '{print $1}')

  local cache_file="${CACHE_DIR}/vendor-php-${lock_checksum}.tar.gz"

  if [ -n "$lock_checksum" ] && [ -f "$cache_file" ]; then
    log "Composer cache hit ($lock_checksum): extracting vendor"
    rm -rf "${vendor_dir}"
    tar xzf "$cache_file" -C "${SITE_ROOT}"
  else
    log "Composer cache miss: running composer install"
    time composer install --no-progress -o $*

    if [ -n "$lock_checksum" ]; then
      log "Caching vendor directory for composer.lock ($lock_checksum)"
      tar czf "$cache_file" -C "${SITE_ROOT}" vendor
    fi
  fi
}

function init_npm_dependencies() {
  log "Installing NPM dependencies";

  # Ensure nvm.sh is run before building NPM stuff,
  # so the npm versions it installed are available
  source /opt/nvm/nvm.sh

  time npm ci;

#  local npm_dir="$SITE_ROOT/node_modules"
#  local npm_file='npm.tar.gz'
#  local artifact_file;
#
#  # Only use last line of output from function:
#  # the rest may include logs
#  artifact_file=$(download_artifact "${npm_file}" 'npm' 'npm-shrinkwrap.json' 'node_modules/npm.shrinkwrap.checksum' "$npm_dir" | tail -n1)
#
#  if [ "$artifact_file" = "ignore" ]; then
#      log "NPM artifact up to date";
#  else
#      if [ -f "${CACHE_DIR}/${artifact_file}" ]; then
#          # Extract content
#          log "Found matching NPM cache file: now extracting";
#          rm -fR "$SITE_ROOT"/node_modules 2>/dev/null;
#          rm -fR "${npm_dir}" 2> /dev/null;
#          tar xzf "${CACHE_DIR}/${artifact_file}";
#      else
#          # Still no luck, build from scratch
#          log "Cache content:";
#          cat "${CACHE_DIR}"/npm.cache;
#          log "No matching NPM cache found. Building with npm";
#          "$SITE_ROOT"/tools/pkg-build-npm.sh 2>&1;
#      fi
#  fi

  log "Building JS/CSS files";
  time npm run build
#  "$SITE_ROOT"/tools/pkg-build-css-js.sh
}

function add_rabbitmq_vhost() {
  log "Adding RabbitMQ vhost and permissions";
  curl -u guest:guest -H "Accept: application/json" -X PUT "http://rabbitmq:15672/api/vhosts/${INSTANCE_ID}"
  curl -u guest:guest -H "Accept: application/json" -X PUT -d '{"configure":".*","write":".*","read":".*"}' "http://rabbitmq:15672/api/permissions/${INSTANCE_ID}/guest"
}

function create_data_dirs() {
  log "Creating data dirs for instance";
  /usr/share/schoolbox/tools/create-data-dirs.sh "/var/lib/schoolbox/${INSTANCE_ID}/"
  /usr/share/schoolbox/tools/set-data-dir-perms.sh "/var/lib/schoolbox/${INSTANCE_ID}/" www-data
}

function install_instance() {
  log "Installing instance via 'schoolbox-data-refresh.sh'";

  # Note schoolbox-data-refresh creates the database and then applies the schoolbox/updates SQL and PHP scripts.
  # It is the responsibility of schoolbox-data-refresh to enable and disable maintenance mode as appropriate during its
  # execution (by making calls to site_down.sh/site_up.sh).
  # (The site_down.sh/site_up.sh scripts update the database, so site_down.sh cannot be run until the database has been
  #  created.)
  if ! schoolbox-data-refresh \
    --data-source "$DATA_SOURCE" \
    --dataset "$DATA_FILENAME" \
    --skip-bucket-copy \
    --sql-host "$(schoolbox-read-instances --option SQL_host)" \
    --sql-schema "$(schoolbox-read-instances --option SQL_db)"  \
    --sql-user "$(schoolbox-read-instances --option SQL_user)"  \
    --sql-pass "$(schoolbox-read-instances --option SQL_passwd)"  \
    --cache-dir "${CACHE_DIR}" \
    --config amqp_host "'rabbitmq'" \
    --config amqp_user "'guest'" \
    --config amqp_pass "'guest'" \
    --config amqp_vhost "'${INSTANCE_ID}'" \
    --config debug_show_stack_trace "'1'" \
    --config debug_templates "'1'" \
    --config eotc_enabled "'1'" \
    --config etherpad_host "'http://schoolbox-etherpad:9001'" \
    --config etherpad_api_token "'test'" \
    --config date_timezone "'${CONFIG_DATE_TIMEZONE}'" \
    --config external_type "'$EXTERNAL_DB'" \
    --config external_db_db "'$EXTERNAL_DB_DB'" \
    --config external_db_host "'$EXTERNAL_DB_HOST'" \
    --config external_db_user "'schoolbox'" \
    --config external_portal "'https://portal.synergetic.dev.schoolbox.cloud'" \
    --config fixtures_enabled "'1'" \
    --config gotenberg_host "'http://schoolbox-gotenberg:3000'" \
    --config instance_registry_url "'$INSTANCE_REGISTRY_URL'" \
    --config ldap_enable "'0'" \
    --config mail_enable "'1'" \
    --config mail_smtp_host "'mailcatcher'" \
    --config mail_smtp_port "'1025'" \
    --config office365_client "'85671866-2f6b-4ec3-9940-1f39ecc05e50'" \
    --config office365_immersive_reader_name "'schoolbox-test'" \
    --config office365_tenant "'alaressteam'" \
    --config office365_tenant_id "'9a9eb3a6-27e2-43bf-8831-37e8d0cc2265'" \
    --config pastoral_enabled "'1'" \
    --config qr_login_enabled "'1'" \
    --config redis_url "'tcp://redis-storage:6379'" \
    --config redis_ephemeral_url "'tcp://redis-cache:6379'" \
    --config taskd_redis_url "'tcp://redis-storage:6379'" \
    --config resource_booking_enabled "'1'" \
    --config site_domain "'dev.schoolbox.cloud'" \
    --config site_hostname "'${INSTANCE_ID}'" \
    --config site_school_code "'${INSTANCE_ID}'" \
    --config site_environment "'client-hosted'" \
    --config site_type "'dev'" \
    --config strict_templates "'1'" \
    --config summative_report_enabled "'1'" \
    --config title "'Schoolbox [${INSTANCE_ID}]'" \
    --config ups_groups_enabled "'1'" \
    --config zip_dir "'/var/lib/schoolbox/${INSTANCE_ID}/cache/zip'" \
    --config portrait_dir "'/var/lib/schoolbox/${INSTANCE_ID}/cache/portrait'" \
    --config stream_dir "'/var/lib/schoolbox/${INSTANCE_ID}/cache/stream'" \
    --config storage_dir "'/var/lib/schoolbox/${INSTANCE_ID}/storage'" \
    --config logs_dir "'/var/log/schoolbox/${INSTANCE_ID}'" \
    --config purifier_cache_dir "'/var/lib/schoolbox/${INSTANCE_ID}/purifier'" \
    --config twig_cache_dir "'/var/lib/schoolbox/${INSTANCE_ID}/twig'" \
    --config backup_dir "'/var/lib/schoolbox/${INSTANCE_ID}/backups'" \
    --config presidio_analysis_host "'http://presidio-analyzer:3000'" \
    --config presidio_anonymisation_host "'http://presidio-anonymizer:3000'";
  then
    error "Unable to install instance. Exiting."
    exit 1
  fi

  log "Applying development environment SQL fixes";
  "$SITE_ROOT"/tools/instance-mariadb.sh < "${SITE_ROOT}/sql/vagrant.sql"
  "$SITE_ROOT"/tools/instance-mariadb.sh < "${CACHE_DIR}/vagrant-secrets.sql"

  log "Loading all demo data complete";
}

function post_install() {
  log "Removing system maintenance mode";
  if "$SITE_ROOT"/tools/maintenance/site_up.sh -eq 0 ; then
    log "Maintenance mode disabled on instance '${INSTANCE_ID}'"
  else
    warn "Could not disable maintenance mode on instance 'docker': continuing"
  fi

  # call custom provisioning
  if [ -f "$SITE_ROOT"/post-provision.sh ];
  then
      log "Running custom post provisioning";
      "$SITE_ROOT"/post-provision.sh;
  fi

  # update all dates to current time
  "$SITE_ROOT"/tools/increment-instance-dates.php;

  # Clean up vcal cache
  "$SITE_ROOT"/tools/redis/removeVcalCache.php -p;
}

function main() {
  load_consts
  verify_env_vars
  verify_consts
  load_and_verify_config
  setup_aws_sso

  # Should the setup of everything be skipped?
  install_instance_only='0'
  errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
      --install-instance-only )
        install_instance_only='1'
        shift
        ;;
      *)
        error "Unrecognised option: '$1'"
        shift # past argument
        errors="1"
        ;;
    esac
  done
  if [[ $errors == "1" ]]; then
    exit 1
  fi

  if [[ $install_instance_only == "0" ]]; then
    cleanup_cache_dir
    download_secrets
    download_certs
    mark_safe_for_git

    init_composer_dependencies
    init_npm_dependencies

    init_database
  else
    log "Skipping setup";
  fi

  add_rabbitmq_vhost
  create_data_dirs
  install_instance
  post_install
}

main "$@"
