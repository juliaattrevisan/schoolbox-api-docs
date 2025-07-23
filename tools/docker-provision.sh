#!/usr/bin/env bash
# Provisions the Docker environment: port of `vagrant-provision.sh`

declare SITE_ROOT
declare cachedir
declare REQUIRED_CONSTS=(SITE_ROOT cachedir)

declare aws_account_id
declare aws_role_name
declare aws_start_url
declare data_source
declare data_filename
declare external_db
declare external_db_host
declare external_db_db
declare instance_registry_url
declare default_instance_id
declare artifact_source
declare REQUIRED_CONFIGS=(\
  aws_account_id aws_role_name aws_start_url \
  data_source data_filename \
  external_db external_db_host external_db_db \
  instance_registry_url \
  default_instance_id \
  artifact_source \
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
      warn "${var}: ${!var} is not a folder";
      return=0
    fi
  done
  return $return
}

function load_consts() {
  # Load global constants and functions:
  # SITE_ROOT is not yet defined, so assume this script is running in what will
  # eventually be SITE_ROOT
  source ./tools/docker-consts.sh

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

function load_config() {
  # Global vars: load from .ini files
  source "${SITE_ROOT}/docker.default.ini"
  if [ -f "${SITE_ROOT}/docker.ini" ]; then
    source "${SITE_ROOT}/docker.ini"
  fi

  log "Verifying configuration:"
  verify_args "${REQUIRED_CONFIGS[@]}"
  if [ $? -eq 0 ]; then
    error "Not all required configs defined: exiting"
    exit 1
  fi
}

# Setup AWS CLI: copied from `vagrant-provision-pre-puppet.sh`
function version_gte() {
  echo -e "$1\n$2" | sort -rCV
}

function setup_aws_sso() {
  if [ -f ~/.aws/credentials ]; then
    if grep -q 'default' ~/.aws/credentials; then
      log "AWS SSO already set up";
      return
    fi
  fi
  log "Setting up AWS SSO";
  mkdir -p ~/.aws
  echo "[default]
  sso_session = docker
  sso_account_id = ${aws_account_id}
  sso_role_name = ${aws_role_name}
  region = ap-southeast-2

  [sso-session docker]
  sso_region = ap-southeast-2
  sso_start_url = ${aws_start_url}" > ~/.aws/config
  params=()
  params+=(--no-browser)
  aws_version=$(aws --version 2>&1 | cut -d " " -f1 | cut -d "/" -f2)
  if version_gte "$aws_version" "2.22.0"; then
    params+=(--use-device-code)
  fi
  if ! aws configure list > /dev/null; then
    aws sso login "${params[@]}"
  fi
}

function cleanup_cache_dir() {
  log "Cleaning up zero byte files";
  find "${cachedir}" -type f -empty -delete;
}

function download_secrets() {
  log "Retrieving latest secrets";
  # Download some files from S3: note these still use the Vagrant names
  if ! $SITE_ROOT/tools/download-s3-file.sh --data-source "$data_source"  --file-name "vagrant-secrets.sql" --cache-dir "$cachedir"; then
    error "Unable to download s3 file: 'vagrant-secrets.sql': exiting";
    exit 1;
  fi
  if ! $SITE_ROOT/tools/download-s3-file.sh --data-source "$data_source" --file-name "vagrant-cleanup-secrets.sql" --cache-dir "$cachedir"; then
    error "Unable to download s3 file: 'vagrant-cleanup-secrets.sql': exiting";
    exit 1;
  fi
}

function mark_safe_for_git() {
  log "Adding Schoolbox dir ${SITE_ROOT} as safe for Git";
  git config --global --add safe.directory "${SITE_ROOT}"
}

function set_profile_env_vars() {
  # Set env vars for root + standard user (if one is added)
  local bashrcFiles=(/root/.bashrc)
  declare -A env_vars=(
    [INSTANCE_ID]="${default_instance_id}"
  )
  for config_name in "${!env_vars[@]}"; do
    config_value=${env_vars[$config_name]}
    for bashrcFile in "${bashrcFiles[@]}"; do
      # Remove the config if it exists, then insert it
      sed -i -E "/^export\s+$config_name\s*=.+/d" ${bashrcFile}
      echo "export $config_name=\"$config_value\"" >> ${bashrcFile};
    done

  #  # Need to preserve these ENV vars for vagrant's sudo session
  #  if ! grep -qE "^Defaults\s+env_keep\s*\+=\s*\".*$config_name" /etc/sudoers.d/vagrant; then
  #    chmod a+w /etc/sudoers.d/vagrant;
  #    echo "Defaults env_keep += \"$config_name\"" >> /etc/sudoers.d/vagrant;
  #    chmod a-w /etc/sudoers.d/vagrant;
  #  fi
  done
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

  # Location of the lock file to check
  local lock_file=$2

  # Location of the checksum
  local checksum_file=$3

  # Location to extract to
  local extract_dir=$4

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
  cache_file="${cachedir}/$artifact_name.cache"
  etag_file="${cachedir}/$artifact_name.etag.cache"

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
                  etag=$(curl -I "${artifact_source}/$branch-${artifact_name}" 2>/dev/null | grep ETag | cut -d'"' -f2);
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
                          wget --no-verbose "${artifact_source}/${version_artifact_name}" -O "${cachedir}/${version_artifact_name}" 2>&1;
                          if [ $? -eq 0 ]; then
                              # This is a new file, so remove old references
                              sed -i "/${version_artifact_name}/d" "$etag_file" 2>/dev/null;
                              sed -i "/${version_artifact_name}/d" "$cache_file" 2>/dev/null;
                              # Add new file to etag cache
                              echo "$etag ${version_artifact_name}" >> "$etag_file";

                              # Cleanup for purity?
                              rm -fR "${cachedir}/${extract_dir:?}" 2> /dev/null;

                              # Extract checksum
                              tar xzf "${cachedir}/${version_artifact_name}" "${checksum_file}";
                              local version_artifact_checksum
                              version_artifact_checksum=`awk '{print $1}' "${extract_dir}/${checksum_file}"`;

                              # Cleanup again
                              rm -fR "${cachedir}/${extract_dir:?}" 2> /dev/null;

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
  log "Installing vendor dependencies with composer";

  local vendor_dir="$SITE_ROOT/vendor"
  local vendor_file='vendor-php-dev.tar.gz'
  local artifact_file;

  # Only use last line of output from function:
  # the rest may include logs
  artifact_file=$(download_artifact "${vendor_file}" 'composer.lock' 'vendor/composer.lock.checksum' "$vendor_dir" | tail -n1)

  if [ "$artifact_file" = "ignore" ]; then
      log "Vendor artifact up to date: updating autoload and patches";
      composer install -o --no-interaction
  else
      if [ -f "${cachedir}/${vendor_file}" ]; then
          # Extract content
          log "Extracting vendor cache file '$vendor_file'";
          rm -fR "${vendor_dir}" 2> /dev/null;
          tar xzf "${cachedir}/${vendor_file}";
          log "Updating autoload and patches";
          composer install -o --no-interaction
      else
          # Still no luck, build from scratch with composer
          log "No matching vendor cache found. Building with composer";
          "$SITE_ROOT"/tools/composer.sh
      fi
  fi

#  cd "$cachedir" || exit 1;
#  local vendorfile='';
#  local composerlocksha;
#  composerlocksha=`sha1sum ../composer.lock 2>/dev/null | awk '{print $1}'`;
#
#  if [ -f "$SITE_ROOT"/vendor/composer.lock.checksum ];
#  then
#      currentvendorsha=`awk '{print $1}' "$SITE_ROOT"/vendor/composer.lock.checksum`;
#      if [ "$currentvendorsha" == "$composerlocksha" ];
#      then
#          log "Vendor build up to date, nothing to change";
#          vendorfile='ignore';
#      fi
#  fi
#
#  if [ "$vendorfile" != 'ignore' ];
#  then
#      local check;
#      check=`grep $composerlocksha vendor.cache 2>/dev/null | wc -l`;
#      if [ $check -gt 0 ];
#      then
#          # Woo - we've located a matching cache file
##          log "Found matching vendor cache file '$vendorfile'";
#          vendorfile=`grep $composerlocksha vendor.cache 2>/dev/null | head -n1 | cut -d' ' -f2-100`;
#      else
#          # No cache files match - boo hoo - check to see if there are new versions
#          log "No matching vendor cache file found: downloading";
#          for branch in develop master;
#          do
#              # Check if we've already found a matching file
#              if [ "$vendorfile" == '' ];
#              then
#                  local etag;
#                  etag=$(curl -I "${artifact_source}/$branch-vendor-php-dev.tar.gz" 2>/dev/null | grep ETag | cut -d'"' -f2);
#                  if [ $? -eq 0 ];
#                  then
#                      local checketag;
#                      checketag=`grep $etag vendor.etag.cache 2>/dev/null | wc -l`;
#                      if [ $checketag -eq 0 ];
#                      then
#                          # New/updated version available - download it
#                          log "Downloading latest vendor build artifact: $branch-vendor-php-dev.tar.gz";
#                          wget --no-verbose "${artifact_source}/$branch-vendor-php-dev.tar.gz" -O $branch-vendor-php-dev.tar.gz 2>&1;
#                          if [ $? -eq 0 ];
#                          then
#                              # This is a new file, so remove old references
#                              sed -i "/$branch-vendor-php-dev.tar.gz/d" vendor.etag.cache 2>/dev/null;
#                              sed -i "/$branch-vendor-php-dev.tar.gz/d" vendor.cache 2>/dev/null;
#                              # Add new file to etag cache
#                              echo "$etag $branch-vendor-php-dev.tar.gz" >> vendor.etag.cache;
#                              # Cleanup for purity
#                              rm -fR "${cachedir}"/vendor 2>/dev/null;
#                              # Extract checksum
#                              tar xzf $branch-vendor-php-dev.tar.gz vendor/composer.lock.checksum;
#                              local vendorchecksum
#                              vendorchecksum=`awk '{print $1}' vendor/composer.lock.checksum`;
#                              rm -fR "${cachedir}"/vendor 2>/dev/null;
#                              # Add new file to vendor cache
#                              echo "$vendorchecksum $branch-vendor-php-dev.tar.gz" >> vendor.cache;
#                              # Check if this is file we need
#                              if [ "$vendorchecksum" == "$composerlocksha" ];
#                              then
#                                  vendorfile="$branch-vendor-php-dev.tar.gz";
#                              fi
#                          fi
#                      fi
#                  fi
#              fi
#          done
#      fi
#      cd "$SITE_ROOT" || exit 1;
#      if [ "$vendorfile" != '' ];
#      then
#          if [ -f "${cachedir}"/$vendorfile ];
#          then
#              # Extract content
#              log "Extracting vendor cache file '$vendorfile'";
#              rm -fR "$SITE_ROOT"/vendor 2>/dev/null;
#              tar xzf "${cachedir}"/$vendorfile;
#          else
#              # Still no luck, build from scratch with composer
#              log "No matching vendor cache found. Building with composer";
#              "$SITE_ROOT"/tools/composer.sh
#          fi
#      else
#          # Still no luck, build from scratch with composer
#          log "No matching vendor cache found. Building with composer";
#          "$SITE_ROOT"/tools/composer.sh
#      fi
#  fi
#  cd "$SITE_ROOT" || exit;
#
#  # Composer autoload needs to apply patches as well:
#  # just use composer install for that, rather than dump-autoload
#  log "Updating composer autoload and patches";
#  composer install -o --no-interaction
}

function install_instance() {
  log "Installing instance via 'schoolbox-data-refresh.sh'";

  # Note schoolbox-data-refresh creates the database and then applies the schoolbox/updates SQL and PHP scripts.
  # It is the responsibility of schoolbox-data-refresh to enable and disable maintenance mode as appropriate during its
  # execution (by making calls to site_down.sh/site_up.sh).
  # (The site_down.sh/site_up.sh scripts update the database, so site_down.sh cannot be run until the database has been
  #  created.)
  if ! INSTANCE_ID="${default_instance_id}" schoolbox-data-refresh \
  --data-source "$data_source" \
  --dataset "$data_filename" \
  --skip-bucket-copy \
  --sql-host 'mariadb' \
  --sql-schema 'schoolbox' \
  --sql-user 'schoolboxdocker' \
  --sql-pass 'abc123' \
  --cache-dir "${cachedir}" \
  --config amqp_host "'rabbitmq'" \
  --config amqp_user "'guest'" \
  --config amqp_pass "'guest'" \
  --config amqp_vhost "'/'" \
  --config debug_show_stack_trace "'1'" \
  --config debug_templates "'1'" \
  --config eotc_enabled "'1'" \
  --config etherpad_host "'http://schoolbox-etherpad:9001'" \
  --config etherpad_api_token "'test'" \
  --config external_type "'$external_db'" \
  --config external_db_db "'$external_db_db'" \
  --config external_db_host "'$external_db_host'" \
  --config external_db_user "'schoolbox'" \
  --config external_portal "'https://portal.synergetic.dev.schoolbox.cloud'" \
  --config fixtures_enabled "'1'" \
  --config gotenberg_host "'http://schoolbox-gotenberg:3000'" \
  --config instance_registry_url "'$instance_registry_url'" \
  --config ldap_enable "'0'" \
  --config mail_enable "'1'" \
  --config mail_smtp_host "'127.0.0.1'" \
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
  --config site_hostname "'docker'" \
  --config site_school_code "'docker'" \
  --config site_environment "'client-hosted'" \
  --config site_type "'dev'" \
  --config strict_templates "'1'" \
  --config summative_report_enabled "'1'" \
  --config title "'Schoolbox [docker]'" \
  --config ups_groups_enabled "'1'"; then
    error "Unable to install instance. Exiting."
    exit 1
  fi

  log "Applying development environment SQL fixes";
  mariadb -h mariadb -u root -D schoolbox < "${SITE_ROOT}/sql/vagrant.sql"
  mariadb -h mariadb -u root -D schoolbox < "${cachedir}/vagrant-secrets.sql"

  log "Loading all demo data complete";
}

function post_install() {
  log "Removing system maintenance mode";
  if INSTANCE_ID="${default_instance_id}" "$SITE_ROOT"/tools/maintenance/site_up.sh -eq 0 ; then
    log "Maintenance mode disabled on instance '${default_instance_id}'"
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

function init_npm_dependencies() {
  # Ensure nvm.sh is run before building NPM stuff,
  # so the npm versions it installed are available
  source ~/.nvm/nvm.sh

  # TODO this is very similar to the Composer download: should attempt to reuse

  log "Building NPM";
  cd "$cachedir" || exit 1;
  local npmfile='';
  local npmlocksha;
  npmlocksha=`sha1sum "$SITE_ROOT"/npm-shrinkwrap.json 2>/dev/null | awk '{print $1}'`;
  if [ -f "$SITE_ROOT"/node_modules/npm.shrinkwrap.checksum ];
  then
      local currentnpmsha;
      currentnpmsha=`awk '{print $1}' "$SITE_ROOT"/node_modules/npm.shrinkwrap.checksum`;
      if [ "$currentnpmsha" == "$npmlocksha" ];
      then
          log "NPM build up to date, nothing to change ($npmlocksha)";
          npmfile='ignore';
      fi
  fi

  if [ "$npmfile" != 'ignore' ];
  then
      log "NPM build not correct or missing ($npmlocksha)";
      local check;
      check=`grep $npmlocksha npm.cache 2>/dev/null | wc -l`;
      if [ $check -gt 0 ];
      then
          # Woo - we've located a matching cache file
#          local npmfile;
          npmfile=`grep $npmlocksha npm.cache 2>/dev/null | head -n1 | cut -d' ' -f2-100`;
      else
          # No cache files match - boo hoo - check to see if there are new versions
          for branch in develop master;
          do
              # Check if we've already found a matching file
              if [ "$npmfile" == '' ];
              then
                  etag=$(curl -I "${artifact_source}/$branch-npm.tar.gz" 2>/dev/null | grep ETag | cut -d'"' -f2);
                  if [ $? -eq 0 ];
                  then
                      local checketag;
                      checketag=`grep $etag npm.etag.cache 2>/dev/null | wc -l`;
                      if [ $checketag -eq 0 ];
                      then
                          # New/updated version available - download it
                          log "Downloading latest NPM build: $branch-npm.tar.gz";
                          wget --no-verbose "${artifact_source}/$branch-npm.tar.gz" -O $branch-npm.tar.gz 2>&1;
                          if [ $? -eq 0 ];
                          then
                              # This is a new file, so remove old references
                              sed -i "/$branch-npm.tar.gz/d" npm.etag.cache 2>/dev/null;
                              sed -i "/$branch-npm.tar.gz/d" npm.cache 2>/dev/null;
                              # Add new file to etag cache
                              echo "$etag $branch-npm.tar.gz" >> npm.etag.cache;
                              # Cleanup for purity
                              rm -fR "${cachedir}"/node_modules 2>/dev/null;
                              # Extract checksum
                              tar xzf $branch-npm.tar.gz node_modules/npm.shrinkwrap.checksum;
                              local npmchecksum;
                              npmchecksum=`awk '{print $1}' node_modules/npm.shrinkwrap.checksum`;
                              rm -fR "${cachedir}"/node_modules 2>/dev/null;
                              # Add new file to NPM cache
                              echo "$npmchecksum $branch-npm.tar.gz" >> npm.cache;
                              # Check if this is file we need
                              if [ "$npmchecksum" == "$npmlocksha" ];
                              then
                                  npmfile="$branch-npm.tar.gz";
                              fi
                          fi
                      fi
                  fi
              fi
          done
      fi
      cd "$SITE_ROOT" || exit 1;
      if [ "$npmfile" != '' ];
      then
          if [ -f "${cachedir}"/$npmfile ];
          then
              # Extract content
              log "Found matching NPM cache file ($npmlocksha) '$npmfile', now extracting";
              rm -fR "$SITE_ROOT"/node_modules 2>/dev/null;
              tar xzf "${cachedir}"/$npmfile;
          else
              # Still no luck, build from scratch with npm
              log "Cache content:";
              cat "${cachedir}"/npm.cache;
              log "No matching NPM cache found. Building with npm";
              "$SITE_ROOT"/tools/pkg-build-npm.sh 2>&1;
          fi
      else
          # Still no luck, build from scratch with npm
          log "Cache content:";
          cat "${cachedir}"/npm.cache;
          log "No matching NPM cache found. Building with npm";
          "$SITE_ROOT"/tools/pkg-build-npm.sh 2>&1;
      fi
  fi
  cd "$SITE_ROOT" || exit 1;

  log "Building JS/CSS files";
  "$SITE_ROOT"/tools/pkg-build-css-js.sh
}

function main() {
  load_consts
  load_config
  setup_aws_sso

  cleanup_cache_dir
  download_secrets
  mark_safe_for_git
  set_profile_env_vars

  init_composer_dependencies
  init_npm_dependencies

  init_database
  install_instance
  post_install
}

main "$@"
