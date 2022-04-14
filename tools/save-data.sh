#!/bin/bash

# Global const
readonly CACHE_DIR='/usr/share/schoolbox/vagrantcache';
readonly BUCKET='schoolbox-data';
readonly INSTANCE_DATA_DIR='/var/lib/schoolbox/vagrantx';  # Location of 'vagrantx' instance data files
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200;
readonly L_ERROR=400

# Global vars used across multiple functions
verbose='';        # [''|'v']
long_verbose='';   # [''|'-v']
initial='';        # [''|'1']
upload='';         # [''|'1']
filename='';       # eg 'initial.tar.gz', 'sales.tar.gz', 'custom-dataset-xyz.tar.gz'

function log(){
  $LOG $L_INFO "$1"
}
function error(){
  $LOG $L_ERROR "$1"
}

# Checks script is being run as root or sudo
check_run_as_root(){
  if [[ $EUID != "0" ]]; then
    echo "Please execute script as sudo or root user" >&2;
    exit 1;
  fi
}

# Print a help message to STDOUT
help_msg() {
  echo "save-data.sh OPTIONS";
  echo "Options:";
  echo "       --name [X]  Required. Name of the dataset to persist, eg 'sales', 'initial', 'custom-dataset-x'"
  echo "  -h | --help      Optional. Display this help message";
  echo "  -i | --initial   Optional. Prepare data for using as an initial dataset (more cleaning)";
  echo "  -u | --upload    Optional. Upload the data for others to use on completion. Sync storage files to S3";
  echo "  -v | --verbose   Optional. Verbose mode for easier debugging";
}

# Stop nginx, daemons, cron
stop_services() {
  log "Stopping Services";
  service nginx stop
  /usr/share/schoolbox/tools/notif/allstop
  find /etc/cron.d -maxdepth 1 -iname 'sbc_*' -type f -delete
}

# Restart nginx, daemons, cron
restart_services() {
  log "Restarting Services";
  service nginx restart
  /usr/share/schoolbox/tools/notif/allrestart
  /usr/local/bin/schoolboxcloud-rebuild-cronjobs --is-primary-host 1
}

# Prepare instance to be archived so it can be deployed to new SB environments
cleanup_vagrant() {
  log "Reverting vagrant-specific changes";
  mysql --defaults-extra-file=~/.my.cnf schoolbox < /usr/share/schoolbox/sql/vagrant-cleanup.sql;
  mysql --defaults-extra-file=~/.my.cnf schoolbox < $CACHE_DIR/vagrant-cleanup-secrets.sql;
  log "Applying data dir sql changes";
  /usr/share/schoolbox/tools/generate-dir-sql.sh /var/lib/schoolbox | mysql --defaults-extra-file=/root/.my.cnf schoolbox

  # These files are not migrated by s3-migration tool, so will cause issues if we use the generated dataset with S3
  log "Deleting invalid 'storage_transform' rows"
  mysql --defaults-extra-file=~/.my.cnf schoolbox -e "DELETE st FROM storage_transform st LEFT JOIN storage s ON st.hash = s.hash WHERE s.storage_id IS NULL";
}

# Update instance to act like a vagrant development environment
localise_vagrant() {
  log "Re-localising vagrant"
  mysql schoolbox < /usr/share/schoolbox/sql/vagrant.sql;
  mysql schoolbox < "${CACHE_DIR}"/vagrant-secrets.sql;
  log "Applying data dir sql changes";
  /usr/share/schoolbox/tools/generate-dir-sql.sh "$INSTANCE_DATA_DIR" | mysql --defaults-extra-file=/root/.my.cnf schoolbox
}

# Parse CLI args
parse_args(){
 local errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
    -h|--help)
      help_msg
      exit;
      ;;
    -i|--initial)
      initial='1';
      shift
      ;;
    -u|--upload)
      upload='1';
      shift
      ;;
    -v|--verbose)
      verbose='v'
      long_verbose='-v';
      shift
      ;;
    --name)
      dataset="$2";
      shift; shift;
      ;;
    *)
      error "Unrecognised option: '$1'"
      errors='1'
      shift # past argument
      ;;
  esac
  done

  # Check dataset name is sane
  if [[ -z $dataset ]]; then
    error "Error: You must indicate a dataset name using the --name option";
    errors='1'
  elif [[ ! $dataset =~ ^[a-zA-Z0-9]+[a-zA-Z0-9_-]*$ ]]; then
    error "Error: Dataset name must start with alphanumeric and only contain [a-zA-Z0-9_-]";
    errors='1'
  fi
  filename="$dataset.tar.gz";

  if [[ $errors == 1 ]]; then
    error "Error: Incorrect options provided";
    help_msg
    exit 1
  fi
}

# Check we are saving data from a stable release point
check_is_stable(){
  cd /usr/share/schoolbox || exit 1
  git config --global --add safe.directory /usr/share/schoolbox
  tag=$(git log --oneline --decorate -n 1 | grep -oE 'tag: [0-9a-zA-Z.-]+')
  if [[ -n $tag ]]; then
    echo "Branch HEAD appears to be stable. $tag"
  else
    git log --oneline --decorate -n 1
    echo "It appears your branch HEAD is not tagged as stable."
    echo "Saving from unstable is dangerous as update scripts may subsequently be merged into this release."
    echo "These update scripts will fail to run if deployed to an instance already at this release"
    read -r -p "Continue? [y/n]: " choice
    while ! [[ $choice =~ [ynYN] ]]; do
      read -r -p "Invalid choice. Please enter 'y' or 'n': " choice
    done
    if [[ $choice =~ [nN] ]]; then
      exit
    fi
  fi
}

# Additional cleaning for initial datasets
cleanup_initial_dataset(){
  if [ -n "$initial" ]; then
    log "Cleaning data for Initial dataset";
    log "Cleaning: log tables";
    for table_name in cmsLog user_activity_log login_log msg_reroute_override user_assume_log user_password_reset_request ups_log; do
      mysql --defaults-extra-file=~/.my.cnf schoolbox -e "TRUNCATE $table_name";
    done
  fi
}
truncate_instance_registry_table(){
    mysql schoolbox -e "TRUNCATE instance_registry";
}

# Cleanup deleted + log files
cleanup_deleted_and_log_files(){
  log "Cleaning data";
  log "Cleaning: deleted files";
  /usr/share/schoolbox/tools/gc-storage.php $long_verbose -n 10000000 -p;
  log "Cleaning: log and cache files";
  rm -f "$long_verbose" "$INSTANCE_DATA_DIR"/*.log{,.gz};
  rm -f "$long_verbose" -R "$INSTANCE_DATA_DIR"/cache/logs;
  rm -f "$long_verbose" -R "$INSTANCE_DATA_DIR"/storage/css;
  /usr/share/schoolbox/tools/clean-cache-dir.sh
  /usr/share/schoolbox/tools/create-data-dirs.sh "$INSTANCE_DATA_DIR"
  log "Cleaning: old sql backups";
  rm -f "$long_verbose" "$INSTANCE_DATA_DIR"/*.sql{,.gz};
}

# Dump database to file
dump_db_and_restore_vagrant(){
  log "Exporting database";
  if ! mysqldump --defaults-extra-file=~/.my.cnf -f --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c schoolbox > "$INSTANCE_DATA_DIR"/schoolbox.sql; then
    error "Error: exporting database failed";
    localise_vagrant;
    restart_services;
    exit 1;
  fi

  localise_vagrant;
}

# Create the tarball archive
create_tarball(){
  log "Creating tarball package";
  # To avoid needing to change the tarball deployment code, we will use a temporary symlink to insert the
  # instance's data into the tarball without an instance_id dir prefix.
  rm -rf /tmp/data
  ln -s "$INSTANCE_DATA_DIR" /tmp/data
  local archive=$CACHE_DIR/"$filename"
  if ! tar -C /tmp -f "$archive" -czh$verbose data; then
    error "Error: tarball creation failed";
    restart_services;
    rm -rf /tmp/data
    exit 1;
  fi
  rm -rf /tmp/data
  du -sh $CACHE_DIR/"$filename";
}

# Upload tarball archive to AWS S3
upload_tarball(){
  if [ -z "$upload" ]; then
    return;
  fi
  echo -e "AWS S3 Key: \c" && read -r S3KEY;
  if [ -z "$S3KEY" ]; then
    error "Error: an AWS S3 Key must be supplied";
    restart_services;
    exit 1;
  fi
  echo -e "AWS S3 Secret: \c" && read -r S3SECRET;
  if [ -z "$S3SECRET" ]; then
    error "Error: an AWS S3 Secret must be supplied";
    restart_services;
  exit 1;
  fi
  # below is based on: https://gist.github.com/chrismdp/6c6b6c825b07f680e710
  path=$CACHE_DIR;
  date=$(date +"%a, %d %b %Y %T %z");
  acl="x-amz-acl:public-read";
  content_type='application/x-compressed-tar';
  string="PUT\n\n$content_type\n$date\n$acl\n/${BUCKET}/${filename}";
  signature=$(echo -en "${string}" | openssl sha1 -hmac "${S3SECRET}" -binary | base64)

  if response=$(curl $long_verbose -X PUT -T "$path/$filename" \
      -H "Host: $BUCKET.s3-ap-southeast-2.amazonaws.com" \
      -H "Date: $date" \
      -H "Content-Type: $content_type" \
      -H "$acl" \
      -H "Authorization: AWS ${S3KEY}:$signature" \
      -H "Accept: application/json" \
      "https://$BUCKET.s3-ap-southeast-2.amazonaws.com/${filename}"); then
    if [[ -z $response ]]; then
      log "Upload of $filename to S3 completed";
    else
      error "Error: an error occurred uploading to S3";
      echo -e "$response" >&2;
    fi
  else
    error "Error: an error occurred uploading to S3";
  fi

  log "Uploading storage files to S3"
  INSTANCE_ID=vagrantx /usr/share/schoolbox/tools/s3-migration.php --quiet --perform
}

main(){
  check_run_as_root
  parse_args "$@"
  check_is_stable
  log "Saving data as: '$dataset.tar.gz'";
  mkdir -p $CACHE_DIR;
  cleanup_initial_dataset
  truncate_instance_registry_table
  cleanup_deleted_and_log_files
  cleanup_vagrant
  stop_services;
  dump_db_and_restore_vagrant
  create_tarball
  upload_tarball
  restart_services;
  log "Save complete";
}

main "$@"
