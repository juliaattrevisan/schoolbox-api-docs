#!/bin/bash

readonly LOG="/usr/bin/alaress-logger"
readonly L_ERROR=400

error() {
  $LOG $L_ERROR "$1"
}

parse_args() {
  local errors='0'
  if [[ -z $INSTANCE_ID ]]; then
    error "No INSTANCE_ID PROVIDED"
    errors='1'
  fi

  if [[ ! -f "/etc/schoolbox/instances.ini" ]]; then
    error "File not found: '/etc/schoolbox/instances.ini'"
    errors='1'
  fi
  if [[ "$errors" == '1' ]]; then
    error "Errors detected, exiting"
    exit 1
  fi
}

fix_ids() {
  sql_host=$(schoolbox-read-instances --option SQL_host);
  sql_user=$(schoolbox-read-instances --option SQL_user);
  sql_pass=$(schoolbox-read-instances --option SQL_passwd);
  sql_db=$(schoolbox-read-instances --option SQL_db);

  if ! mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "UPDATE user SET synergy_id = id WHERE COALESCE(synergy_id, '') = ''"; then
    error "failed to set IDs"
  fi
}

main (){
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  fix_ids
}

main "$@"
