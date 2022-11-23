#!/bin/bash
# Perform mysql optimize for a single instance
# Usage:
#  INSTANCE_ID=xxxxxxxx tools/schema/optimize.sh

# Global constants
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400

# Global vars
errors=0 # Were any errors encountered during script operation?

# Consistently log messages
log() {
  $LOG $L_INFO "$1"
}
warn() {
  $LOG $L_WARN "$1"
}
error() {
  $LOG $L_ERROR "$1"
}

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games

log "Starting optimize..."

ini_file=/etc/schoolbox/instances.ini

if ! db_user=$(schoolbox-read-instances --ini "$ini_file" --option SQL_user) ||
  ! db_pass=$(schoolbox-read-instances --ini "$ini_file" --option SQL_passwd) ||
  ! db_host=$(schoolbox-read-instances --ini "$ini_file" --option SQL_host) ||
  ! db_schema=$(schoolbox-read-instances --ini "$ini_file" --option SQL_db) ||
  [[ -z $db_user ]] ||
  [[ -z $db_pass ]] ||
  [[ -z $db_host ]] ||
  [[ -z $db_schema ]]; then
  error "Unable to derive SQL credentials"
  exit 1
fi

for table in $(mysql -h"$db_host" -u"$db_user" -p"$db_pass" -sse "SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = '$db_schema' AND TABLE_TYPE != 'VIEW' ORDER BY TABLE_NAME"); do
  log "Starting optimize for table: '$table'..."

  # Pipe output to STDOUT, but also capture so we can analyse (OPTIMISE does not provide useful exit codes)
  {
    output=$(mysql --table -h"$db_host" -u"$db_user" -p"$db_pass" "$db_schema" -e "SET MAX_STATEMENT_TIME=0; OPTIMIZE TABLE $table;" | tee /dev/fd/3)
  } 3>&1

  if echo -e "$output" | grep -qE "optimize\s+\|\s+status\s+\|\s+OK"; then
    log "Optimize successfully completed for table: '$table'..."
  else
    error "Optimize failed for table: '$table'..."
    errors=1
  fi
done

log "Finished schema optimize."
exit $errors
